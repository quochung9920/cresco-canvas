# AI Import POC

This document describes the POC import flow added in the `feat/all-features` branch.

Features:
- Import modal for pasting Cresco Session JSON
- Lightweight validation (POC) and preview
- Apply creates a checkpoint via editor callback

How to use:
- Open the editor, trigger the Import modal (you can wire the component into the editor UI for testing)
- Paste a Cresco Session JSON (templates available in /templates)
- Click "Validate & Preview" and then "Apply" to import

Notes:
- The validator is intentionally lightweight for the POC. For production, replace with a JSON Schema + AJV validation and better error messages.
