# 🌍 Zotero Country Tag Map for WordPress

This WordPress plugin creates an **interactive, color-coded world map** based on tagged citations in public Zotero collections — perfect for showcasing global impact, tracking policy references, or visualizing country-specific literature.

---

## ✨ Features

- 🗺️ Displays countries with Zotero-tagged citations using World Bank region names
- 🔗 Clicking a country opens the corresponding Zotero tag view
- 🎨 Fully customizable colors for highlighted countries, borders, and water
- 🧰 Easy manual triggers for refreshing Zotero data and country list
- 📥 Settings import/export (as JSON) for backup or deployment
- 📷 Optional **Export to PNG** button (when enabled in admin)
- 📜 Built-in log viewer and World Bank country list display
- 🧩 Admin settings under **Settings → Zotero Map**

---

## 🛠️ Installation

1. Download the latest release `.zip` file
2. Go to `Plugins → Add New → Upload Plugin` in your WordPress admin
3. Activate the plugin
4. Go to `Settings → Zotero Map` to configure

---

## ⚙️ Configuration

You’ll need:

- **Zotero Group ID** (numeric, from your Zotero group)
- **Library Name** (e.g., `hl_standards`)
- **Collection Keys** (e.g., `2VKW9E87`, one per line)

Zotero citations should be **tagged with country names** that match World Bank naming (e.g., `United States`, `Argentina`, `Bangladesh`, etc.).

---

## 🖼️ Shortcode

Add this shortcode to any page or post:

```[zotero_citation_map]```

## 🧪 Manual Triggers

Logged-in admins can run:
- ?trigger_zotero_map_update — refreshes tag map
- ?trigger_world_bank_update — refreshes the country list from World Bank API

## License

This project is licensed under the [Apache 2.0 License](https://www.apache.org/licenses/LICENSE-2.0).



## Contributing
Contact [Daniel Vreeman](https://github.com/djvreeman) if you're interested in contributing to this project.

