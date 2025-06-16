#!/usr/bin/env python3
"""
Generic Confluence Page Properties to RIS Citation Converter

This script fetches Confluence pages using CQL queries and converts
their page properties (displayed as HTML tables) into RIS citation format.
"""

import requests
import re
import yaml
import argparse
import os
import time
from pathlib import Path
from bs4 import BeautifulSoup
from datetime import datetime
import json
import sys

class ConfluenceRISConverter:
    def __init__(self, config_path="data/config/confluence.yaml"):
        self.config = self.load_config(config_path)
        self.base_url = self.config.get('base_url', 'https://confluence.hl7.org')
        self.session = requests.Session()
        
        # Rate limiting settings
        self.rate_limit_delay = self.config.get('rate_limit_delay', 1.0)  # seconds between requests
        self.max_retries = self.config.get('max_retries', 3)
        self.retry_delay = self.config.get('retry_delay', 5.0)  # seconds to wait on rate limit
        
        # Country/region name transformations to World Bank standard names
        self.country_transformations = {
            'UK': 'United Kingdom',
            'United States': 'United States',
            'US': 'United States',
            'USA': 'United States',
            'Canada': 'Canada',
            'Australia': 'Australia',
            'New Zealand': 'New Zealand',
            'Germany': 'Germany',
            'France': 'France',
            'Netherlands': 'Netherlands',
            'Belgium': 'Belgium',
            'Switzerland': 'Switzerland',
            'Austria': 'Austria',
            'Denmark': 'Denmark',
            'Sweden': 'Sweden',
            'Norway': 'Norway',
            'Finland': 'Finland',
            'Japan': 'Japan',
            'South Korea': 'Korea, Rep.',
            'Brazil': 'Brazil',
            'Argentina': 'Argentina',
            'Chile': 'Chile',
            'Mexico': 'Mexico',
            'Spain': 'Spain',
            'Italy': 'Italy',
            'Portugal': 'Portugal',
            'Ireland': 'Ireland',
            'Scotland': 'United Kingdom',
            'Wales': 'United Kingdom',
            'England': 'United Kingdom',
            'Northern Ireland': 'United Kingdom'
        }
        
        # Set bearer token authentication
        token = self.config.get('bearer_token')
        if token:
            self.session.headers.update({"Authorization": f"Bearer {token}"})
    
    def _make_request_with_retry(self, url, params=None):
        """Make HTTP request with rate limiting and retry logic"""
        for attempt in range(self.max_retries):
            try:
                # Rate limiting - wait between requests
                if attempt > 0 or hasattr(self, '_last_request_time'):
                    time_since_last = time.time() - getattr(self, '_last_request_time', 0)
                    if time_since_last < self.rate_limit_delay:
                        sleep_time = self.rate_limit_delay - time_since_last
                        print(f"  Rate limiting: waiting {sleep_time:.1f}s...")
                        time.sleep(sleep_time)
                
                self._last_request_time = time.time()
                response = self.session.get(url, params=params)
                
                if response.status_code == 429:
                    # Rate limited - wait longer and retry
                    retry_after = response.headers.get('Retry-After', self.retry_delay)
                    try:
                        retry_after = float(retry_after)
                    except:
                        retry_after = self.retry_delay
                    
                    print(f"  Rate limited (429), waiting {retry_after}s before retry {attempt + 1}/{self.max_retries}...")
                    time.sleep(retry_after)
                    continue
                
                response.raise_for_status()
                return response
                
            except requests.RequestException as e:
                if attempt == self.max_retries - 1:
                    # Last attempt failed
                    raise e
                else:
                    print(f"  Request failed (attempt {attempt + 1}/{self.max_retries}): {e}")
                    time.sleep(self.retry_delay)
    
    def load_config(self, config_path):
        """Load configuration from YAML file"""
        try:
            with open(config_path, 'r') as file:
                return yaml.safe_load(file)
        except FileNotFoundError:
            print(f"Config file not found: {config_path}")
            print("Please create a config file with 'base_url' and 'bearer_token'")
            sys.exit(1)
        except yaml.YAMLError as e:
            print(f"Error parsing config file: {e}")
            sys.exit(1)
    
    def search_pages_cql(self, cql_query):
        """Search for pages using raw CQL query"""
        url = f"{self.base_url}/rest/api/content/search"
        params = {
            "cql": cql_query,
            "expand": "metadata.labels",
            "limit": 100
        }
        
        try:
            response = self._make_request_with_retry(url, params)
            return response.json() if response else None
        except requests.RequestException as e:
            print(f"Error searching pages with CQL '{cql_query}': {e}")
            return None
    
    def get_page_content(self, page_id):
        """Get page content with body.view expansion"""
        url = f"{self.base_url}/rest/api/content/{page_id}"
        params = {"expand": "body.view,metadata.labels,space"}
        
        try:
            response = self._make_request_with_retry(url, params)
            return response.json() if response else None
        except requests.RequestException as e:
            print(f"Error getting page {page_id}: {e}")
            return None
    
    def parse_html_table(self, html_content):
        """Parse HTML table from Confluence page properties"""
        soup = BeautifulSoup(html_content, 'html.parser')
        
        # Find the table (usually in a details macro or direct table)
        table = soup.find('table')
        if not table:
            return {}
        
        properties = {}
        rows = table.find_all('tr')
        
        for row in rows:
            cells = row.find_all(['th', 'td'])
            if len(cells) >= 2:
                key = cells[0].get_text(strip=True)
                # Get text content, preserving links if needed
                value_cell = cells[1]
                value = self._extract_cell_value(value_cell)
                properties[key] = value
        
        return properties
    
    def _extract_cell_value(self, cell):
        """Extract value from table cell, handling links and multiple values"""
        # Get all text content
        text_content = cell.get_text(separator='\n', strip=True)
        
        # Also extract links for reference
        links = []
        for link in cell.find_all('a', href=True):
            links.append(link['href'])
        
        # If there are links, include them in the value
        if links:
            return {
                'text': text_content,
                'links': links
            }
        
        return text_content
    
    def normalize_country_name(self, country_name):
        """Transform country/region names to World Bank standard names"""
        if not country_name:
            return country_name
        
        # Clean up the input
        country_name = country_name.strip()
        
        # Check for exact match in transformations
        if country_name in self.country_transformations:
            return self.country_transformations[country_name]
        
        # Check for case-insensitive match
        for key, value in self.country_transformations.items():
            if country_name.lower() == key.lower():
                return value
    
    def sanitize_filename(self, title):
        """Convert page title to lowercase filename-safe string"""
        # Convert to lowercase
        filename = title.lower()
        
        # Replace spaces and special characters with underscores
        filename = re.sub(r'[^\w\-_.]', '_', filename)
        
        # Remove multiple consecutive underscores
        filename = re.sub(r'_+', '_', filename)
        
        # Remove leading/trailing underscores
        filename = filename.strip('_')
        
        return filename
    
    def convert_to_ris(self, page_data, properties, additional_tags=None):
        """Convert page properties to RIS citation format"""
        ris_lines = []
        
        # RIS Type - using 'STD' for standards (Zotero compatible)
        ris_lines.append("TY  - STD")
        
        # Title (Initiative Name or page title)
        title = None
        if 'Initiative Name' in properties:
            title = properties['Initiative Name']
            if isinstance(title, dict):
                title = title['text']
        
        # Fallback to page title if no Initiative Name
        if not title:
            title = page_data.get('title', 'Unknown Title')
        
        ris_lines.append(f"TI  - {title}")
        
        # Author/Organization (Governing Organization)
        if 'Governing Organization' in properties:
            org = properties['Governing Organization']
            if isinstance(org, dict):
                org = org['text']
            ris_lines.append(f"AU  - {org}")
        
        # Date (Initiative Start)
        if 'Initiative Start' in properties:
            date_str = properties['Initiative Start']
            if isinstance(date_str, dict):
                date_str = date_str['text']
            
            # Try to parse and format date
            try:
                # Handle different date formats
                date_formats = ['%B %Y', '%m/%Y', '%Y-%m-%d', '%Y']
                parsed_date = None
                
                for fmt in date_formats:
                    try:
                        parsed_date = datetime.strptime(date_str.strip(), fmt)
                        break
                    except ValueError:
                        continue
                
                if parsed_date:
                    ris_lines.append(f"PY  - {parsed_date.year}")
                    ris_lines.append(f"DA  - {parsed_date.strftime('%Y/%m/%d')}")
            except:
                ris_lines.append(f"PY  - {date_str}")
        
        # Publisher (same as governing org typically)
        if 'Governing Organization' in properties:
            org = properties['Governing Organization']
            if isinstance(org, dict):
                org = org['text']
            ris_lines.append(f"PB  - {org}")
        
        # Abstract/Description (combine relevant fields)
        abstract_parts = []
        if 'Method of Development' in properties:
            method = properties['Method of Development']
            if isinstance(method, dict):
                method = method['text']
            abstract_parts.append(f"Method of Development: {method}")
        
        if 'Adoption Status' in properties:
            status = properties['Adoption Status']
            if isinstance(status, dict):
                status = status['text']
            abstract_parts.append(f"Adoption Status: {status}")
        
        if 'Development Status' in properties:
            dev_status = properties['Development Status']
            if isinstance(dev_status, dict):
                dev_status = dev_status['text']
            abstract_parts.append(f"Development Status: {dev_status}")
        
        if abstract_parts:
            ris_lines.append(f"AB  - {'; '.join(abstract_parts)}")
        
        # Keywords (Type Labels, Topic Labels, Jurisdiction/Region, Additional Tags)
        keywords = []
        
        # Type Labels
        if 'Type Labels' in properties:
            type_labels = properties['Type Labels']
            if isinstance(type_labels, dict):
                type_labels = type_labels['text']
            keywords.extend([label.strip() for label in type_labels.split(',') if label.strip()])
        
        # Topic Labels
        if 'Topic Labels' in properties:
            topic_labels = properties['Topic Labels']
            if isinstance(topic_labels, dict):
                topic_labels = topic_labels['text']
            keywords.extend([label.strip() for label in topic_labels.split(',') if label.strip()])
        
        # Jurisdiction as keyword (with country name normalization)
        if 'Jurisdiction' in properties:
            jurisdiction = properties['Jurisdiction']
            if isinstance(jurisdiction, dict):
                jurisdiction = jurisdiction['text']
            normalized_jurisdiction = self.normalize_country_name(jurisdiction.strip())
            keywords.append(normalized_jurisdiction)
        
        # Region as keyword (with country name normalization)
        if 'Region' in properties:
            region = properties['Region']
            if isinstance(region, dict):
                region = region['text']
            normalized_region = self.normalize_country_name(region.strip())
            keywords.append(normalized_region)
        
        # Additional tags from command line
        if additional_tags:
            keywords.extend(additional_tags)
        
        # Add all keywords
        for keyword in keywords:
            if keyword:
                ris_lines.append(f"KW  - {keyword}")
        
        # URLs (External Links)
        if 'External Links' in properties:
            links_data = properties['External Links']
            if isinstance(links_data, dict) and 'links' in links_data:
                for link in links_data['links']:
                    ris_lines.append(f"UR  - {link}")
        
        # Page URL
        page_url = f"{self.base_url}/pages/viewpage.action?pageId={page_data['id']}"
        ris_lines.append(f"UR  - {page_url}")
        
        # Access date
        access_date = datetime.now().strftime('%Y/%m/%d')
        ris_lines.append(f"Y2  - {access_date}")
        
        # End of record
        ris_lines.append("ER  - ")
        
        return '\n'.join(ris_lines)
    
    def process_pages_cql(self, cql_query, additional_tags=None, output_dir=None):
        """Main method to process pages using CQL and generate RIS citations"""
        print(f"Searching with CQL: {cql_query}")
        
        # Search for pages
        search_results = self.search_pages_cql(cql_query)
        if not search_results:
            print("No search results found")
            return []
        
        citations = []
        pages = search_results.get('results', [])
        
        print(f"Found {len(pages)} pages")
        
        # Create output directory if specified
        if output_dir:
            output_path = Path(output_dir)
            output_path.mkdir(parents=True, exist_ok=True)
        
        for page in pages:
            page_id = page['id']
            page_title = page['title']
            
            print(f"Processing page: {page_title} (ID: {page_id})")
            
            # Get full page content
            page_data = self.get_page_content(page_id)
            if not page_data:
                continue
            
            # Extract HTML content
            body_view = page_data.get('body', {}).get('view', {}).get('value', '')
            if not body_view:
                print(f"  No body content found for page {page_id}")
                continue
            
            # Parse properties from HTML table
            properties = self.parse_html_table(body_view)
            if not properties:
                print(f"  No properties table found for page {page_id}")
                continue
            
            # Convert to RIS
            ris_citation = self.convert_to_ris(page_data, properties, additional_tags)
            
            # Generate filename from page title
            filename = self.sanitize_filename(page_title) + '.ris'
            
            citation_data = {
                'page_id': page_id,
                'title': page_title,
                'filename': filename,
                'properties': properties,
                'ris': ris_citation
            }
            
            # Save individual file if output directory specified
            if output_dir:
                file_path = output_path / filename
                with open(file_path, 'w', encoding='utf-8') as f:
                    f.write(ris_citation)
                print(f"  Saved: {file_path}")
            
            citations.append(citation_data)
            print(f"  Generated RIS citation")
        
        return citations


def create_sample_config():
    """Create a sample configuration file"""
    config_dir = Path("data/config")
    config_dir.mkdir(parents=True, exist_ok=True)
    
    config_path = config_dir / "confluence.yaml"
    
    sample_config = {
        'base_url': 'https://confluence.hl7.org',
        'bearer_token': 'your_personal_access_token_here',
        'rate_limit_delay': 1.0,  # seconds between requests
        'max_retries': 3,
        'retry_delay': 5.0  # seconds to wait on rate limit/errors
    }
    
    with open(config_path, 'w') as f:
        yaml.dump(sample_config, f, default_flow_style=False)
    
    print(f"Sample config created at: {config_path}")
    print("Please edit the file and add your bearer token.")


def main():
    parser = argparse.ArgumentParser(
        description='Convert Confluence page properties to RIS citations',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  %(prog)s -cql 'label="initiative" AND label="fhir_ig"'
  %(prog)s -cql 'space="FHIR" AND type="page"' -tag "FHIR,Healthcare" -o output/
  %(prog)s -cql 'title~"Implementation Guide"' -tag "Standards" -o citations/

Note: Both "Jurisdiction" and "Region" fields are automatically added as keywords
and transformed to World Bank standard country names (e.g., UK -> United Kingdom).
        """
    )
    
    parser.add_argument('-cql', '--cql-query', required=True,
                        help='CQL query string for searching Confluence pages')
    parser.add_argument('-tag', '--tags', 
                        help='Comma-separated list of additional tags to add as keywords')
    parser.add_argument('-o', '--output-dir', 
                        help='Output directory for RIS files (creates individual files per page)')
    parser.add_argument('-c', '--config', default='data/config/confluence.yaml',
                        help='Path to configuration YAML file')
    parser.add_argument('--delay', type=float, 
                        help='Override rate limit delay between requests (seconds)')
    parser.add_argument('--max-retries', type=int,
                        help='Override maximum number of retries for failed requests')
    parser.add_argument('--create-config', action='store_true',
                        help='Create a sample configuration file and exit')
    
    args = parser.parse_args()
    
    # Create sample config if requested
    if args.create_config:
        create_sample_config()
        return
    
    # Parse additional tags
    additional_tags = []
    if args.tags:
        additional_tags = [tag.strip() for tag in args.tags.split(',') if tag.strip()]
    
    # Initialize converter
    try:
        converter = ConfluenceRISConverter(args.config)
        
        # Override rate limiting settings if provided
        if args.delay is not None:
            converter.rate_limit_delay = args.delay
        if args.max_retries is not None:
            converter.max_retries = args.max_retries
            
    except SystemExit:
        return
    
    # Process pages
    citations = converter.process_pages_cql(args.cql_query, additional_tags, args.output_dir)
    
    # Output summary
    print(f"\n{'='*60}")
    print(f"Generated {len(citations)} RIS citations")
    
    if not args.output_dir:
        print("Citations:")
        print(f"{'='*60}")
        for citation in citations:
            print(f"\n--- {citation['title']} ---")
            print(citation['ris'])
    else:
        print(f"Files saved to: {args.output_dir}")
    
    # Also create a combined file
    if citations and args.output_dir:
        combined_path = Path(args.output_dir) / f"all_citations_{datetime.now().strftime('%Y%m%d_%H%M%S')}.ris"
        with open(combined_path, 'w', encoding='utf-8') as f:
            for citation in citations:
                f.write(citation['ris'] + '\n\n')
        print(f"Combined file: {combined_path}")


if __name__ == "__main__":
    main()