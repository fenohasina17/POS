# TODO: Refactor database schema for printer_type

- [x] Examine existing migrations for printers and categories to confirm 'printer_type' field
- [x] Create migration for printer_type table
- [x] Create migration to add printer_type_id to categories table
- [x] Create migration to add printer_type_id to printers table
- [x] Create migration to drop printer_type from printers and categories tables
- [x] Create PrinterType model
- [x] Update Category model to include belongsTo PrinterType relationship
- [x] Update Printer model to include belongsTo PrinterType relationship
- [x] Update CategoryController validation to use printer_type_id
- [x] Update PrinterController validation and logic to use printer_type_id
- [x] Create PrinterTypeSeeder and add to DatabaseSeeder
- [x] Update CategoryFactory to set printer_type_id
- [x] Run migrations to apply changes
- [x] Seed the database with printer types
- [x] Create PrinterTypeController with CRUD operations
- [x] Add API routes for printer-types
- [ ] Test the new relationships and printing logic
