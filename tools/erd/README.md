# Database diagrams

- `prism_schema_full.sql`: complete schema export for inspection.
- `prism_schema_core.sql`: domain schema used by the module splitter.
- `modules/`: seven module SQL files and MySQL Workbench `.mwb` diagrams.

Open `.mwb` files in MySQL Workbench or reverse-engineer the corresponding SQL into a model. The diagram files are documentation; install the application using Laravel migrations in the [setup guide](../../docs/SETUP.md).

To regenerate module SQL after reviewing the core schema:

```bash
python tools/erd/build_modules.py
```

The script writes files under `tools/erd/modules/`. Review generated differences before committing. Cross-module relationships may appear as context; Laravel migrations and application rules remain authoritative.
