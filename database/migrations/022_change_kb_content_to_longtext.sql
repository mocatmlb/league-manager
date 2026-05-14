-- Migration: Change knowledge_base.content to LONGTEXT for large rulebook sections

ALTER TABLE knowledge_base MODIFY content LONGTEXT NOT NULL;
