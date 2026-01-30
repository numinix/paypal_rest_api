<?php
/**
 * Database upgrade script to add billing address fields to saved_credit_cards_recurring table
 *
 * This makes subscriptions independent by storing their own billing address
 * instead of relying on lookups from customer or order tables.
 *
 * Run this script once to upgrade the database schema.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

// This is a standalone SQL script that can be run directly on the database
// or included in an installation/upgrade process

-- Add billing address columns to saved_credit_cards_recurring table
ALTER TABLE saved_credit_cards_recurring 
ADD COLUMN IF NOT EXISTS billing_name VARCHAR(255) DEFAULT NULL COMMENT 'Billing contact name',
ADD COLUMN IF NOT EXISTS billing_company VARCHAR(255) DEFAULT NULL COMMENT 'Billing company name',
ADD COLUMN IF NOT EXISTS billing_street_address VARCHAR(255) DEFAULT NULL COMMENT 'Billing street address',
ADD COLUMN IF NOT EXISTS billing_suburb VARCHAR(255) DEFAULT NULL COMMENT 'Billing suburb/address line 2',
ADD COLUMN IF NOT EXISTS billing_city VARCHAR(255) DEFAULT NULL COMMENT 'Billing city',
ADD COLUMN IF NOT EXISTS billing_state VARCHAR(255) DEFAULT NULL COMMENT 'Billing state/province',
ADD COLUMN IF NOT EXISTS billing_postcode VARCHAR(255) DEFAULT NULL COMMENT 'Billing postal code',
ADD COLUMN IF NOT EXISTS billing_country_id INT(11) DEFAULT NULL COMMENT 'Billing country ID (FK to countries table)',
ADD COLUMN IF NOT EXISTS billing_country_code CHAR(2) DEFAULT NULL COMMENT 'Billing country ISO code (CA, US, etc.)';

-- Add shipping information columns
ALTER TABLE saved_credit_cards_recurring
ADD COLUMN IF NOT EXISTS shipping_method VARCHAR(255) DEFAULT NULL COMMENT 'Shipping method name',
ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(15,4) DEFAULT NULL COMMENT 'Shipping cost at time of order';
