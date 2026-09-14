-- Ensures pgvector is available on first boot of the PostgreSQL 17 container.
CREATE EXTENSION IF NOT EXISTS vector;
