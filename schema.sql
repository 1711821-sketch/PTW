-- PostgreSQL Schema for Time Tracking System
-- This file documents the database schema for work order time tracking

-- Users table - migrated from users.json
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'entreprenor', 'drift', 'opgaveansvarlig')),
    approved BOOLEAN DEFAULT FALSE,
    entreprenor_firma VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Work orders table - migrated from wo_data.json
CREATE TABLE IF NOT EXISTS work_orders (
    id SERIAL PRIMARY KEY,
    work_order_no VARCHAR(50),
    p_number VARCHAR(50),
    mps_nr VARCHAR(50),
    description TEXT,
    p_description TEXT,
    jobansvarlig VARCHAR(100),
    telefon VARCHAR(20),
    oprettet_af VARCHAR(50),
    oprettet_dato DATE,
    components TEXT,
    entreprenor_firma VARCHAR(100),
    entreprenor_kontakt VARCHAR(100),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'completed', 'cancelled')),
    latitude REAL,  -- Changed from DECIMAL to support both geographic (55.2) and image coordinates (0-4967)
    longitude REAL, -- Changed from DECIMAL to support both geographic (11.2) and image coordinates (0-7021)
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Approvals table for tracking approvals (replaces JSON approvals object)
CREATE TABLE IF NOT EXISTS approvals (
    id SERIAL PRIMARY KEY,
    work_order_id INTEGER REFERENCES work_orders(id) ON DELETE CASCADE,
    role VARCHAR(20) NOT NULL CHECK (role IN ('entreprenor', 'opgaveansvarlig', 'drift')),
    approved_date DATE,
    approved_by VARCHAR(50),
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(work_order_id, role)
);

-- Time entries table for daily hour tracking
CREATE TABLE IF NOT EXISTS time_entries (
    id SERIAL PRIMARY KEY,
    work_order_id INTEGER NOT NULL REFERENCES work_orders(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    entry_date DATE NOT NULL,
    hours DECIMAL(4,2) NOT NULL CHECK (hours >= 0 AND hours <= 24),
    description TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_daily_entry UNIQUE(work_order_id, user_id, entry_date)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_time_entries_work_order ON time_entries(work_order_id);
CREATE INDEX IF NOT EXISTS idx_time_entries_user ON time_entries(user_id);
CREATE INDEX IF NOT EXISTS idx_time_entries_date ON time_entries(entry_date);
CREATE INDEX IF NOT EXISTS idx_approvals_work_order ON approvals(work_order_id);
CREATE INDEX IF NOT EXISTS idx_work_orders_status ON work_orders(status);
CREATE INDEX IF NOT EXISTS idx_work_orders_firma ON work_orders(entreprenor_firma);

-- Create trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_time_entries_updated_at
    BEFORE UPDATE ON time_entries
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();