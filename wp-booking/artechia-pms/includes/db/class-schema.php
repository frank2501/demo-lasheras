<?php
/**
 * Database schema definitions for all Artechia PMS tables.
 */

namespace Artechia\PMS\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Schema {

    /**
     * Return the full set of CREATE TABLE statements for dbDelta.
     */
    public static function get_tables(): string {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix . ARTECHIA_PMS_PREFIX;

        return "
CREATE TABLE {$p}properties (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  slug varchar(255) NOT NULL,
  description text,
  address varchar(500) DEFAULT '',
  timezone varchar(50) DEFAULT 'America/Argentina/Buenos_Aires',
  currency char(3) DEFAULT 'ARS',
  settings_json longtext,
  is_demo tinyint(1) DEFAULT 0,
  status varchar(20) DEFAULT 'active',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_slug (slug)
) {$charset};

CREATE TABLE {$p}room_types (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  property_id bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL,
  slug varchar(255) NOT NULL,
  description text,
  max_adults tinyint(3) unsigned DEFAULT 2,
  max_children tinyint(3) unsigned DEFAULT 0,
  max_occupancy tinyint(3) unsigned DEFAULT 2,
  base_occupancy tinyint(3) unsigned DEFAULT 2,
  amenities_json longtext,
  photos_json longtext,
  sort_order int(11) DEFAULT 0,
  status varchar(20) DEFAULT 'active',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_property (property_id),
  UNIQUE KEY idx_slug_prop (property_id,slug)
) {$charset};

CREATE TABLE {$p}room_units (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  room_type_id bigint(20) unsigned NOT NULL,
  property_id bigint(20) unsigned NOT NULL,
  name varchar(100) NOT NULL,
  notes text,
  status varchar(30) DEFAULT 'available',
  housekeeping varchar(20) DEFAULT 'clean',
  sort_order int(11) DEFAULT 0,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_room_type (room_type_id),
  KEY idx_property (property_id),
  KEY idx_status (status)
) {$charset};

CREATE TABLE {$p}rate_plans (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  property_id bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL,
  code varchar(50) DEFAULT '',
  description text,
  date_from date DEFAULT NULL,
  date_to date DEFAULT NULL,
  priority tinyint(3) unsigned DEFAULT 0,
  min_stay tinyint(3) unsigned DEFAULT 1,
  max_stay smallint(5) unsigned DEFAULT 30,
  is_annual tinyint(1) DEFAULT 0,
  color char(7) DEFAULT '#3b82f6',
  cancellation_policy_json longtext,
  cancellation_type varchar(20) DEFAULT 'flexible',
  cancellation_deadline_days int(11) DEFAULT 0,
  penalty_type varchar(20) DEFAULT 'none',
  penalty_value decimal(12,2) DEFAULT 0.00,
  deposit_pct decimal(5,2) DEFAULT 0.00,
  is_refundable tinyint(1) DEFAULT 1,
  status varchar(20) DEFAULT 'active',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_property (property_id),
  KEY idx_priority (priority)
) {$charset};

CREATE TABLE {$p}rate_plan_dates (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  rate_plan_id bigint(20) unsigned NOT NULL,
  date_from date NOT NULL,
  date_to date NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_rate_plan (rate_plan_id),
  KEY idx_dates (date_from,date_to)
) {$charset};

CREATE TABLE {$p}rates (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  room_type_id bigint(20) unsigned NOT NULL,
  rate_plan_id bigint(20) unsigned NOT NULL,
  price_per_night decimal(12,2) NOT NULL,
  extra_adult decimal(12,2) DEFAULT 0.00,
  extra_child decimal(12,2) DEFAULT 0.00,
  single_use_discount decimal(5,2) DEFAULT 0.00,
  min_stay tinyint(3) unsigned DEFAULT 1,
  max_stay smallint(5) unsigned DEFAULT 365,
  closed_to_arrival tinyint(1) DEFAULT 0,
  closed_to_departure tinyint(1) DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_type_plan (room_type_id,rate_plan_id),
  KEY idx_rate_plan (rate_plan_id)
) {$charset};

CREATE TABLE {$p}daily_rates (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  room_type_id bigint(20) unsigned NOT NULL,
  rate_plan_id bigint(20) unsigned NOT NULL,
  rate_date date NOT NULL,
  price_per_night decimal(12,2) DEFAULT NULL,
  extra_adult decimal(12,2) DEFAULT NULL,
  extra_child decimal(12,2) DEFAULT NULL,
  min_stay tinyint(3) unsigned DEFAULT NULL,
  max_stay_override smallint(5) unsigned DEFAULT NULL,
  closed_to_arrival tinyint(1) DEFAULT NULL,
  closed_to_departure tinyint(1) DEFAULT NULL,
  closed tinyint(1) DEFAULT 0,
  available_units smallint(5) DEFAULT NULL,
  stop_sell tinyint(1) DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_type_plan_date (room_type_id,rate_plan_id,rate_date),
  KEY idx_date (rate_date)
) {$charset};

CREATE TABLE {$p}extras (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  property_id bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL,
  description text,
  price decimal(12,2) NOT NULL,
  price_type varchar(30) DEFAULT 'per_booking',
  max_qty tinyint(3) unsigned DEFAULT 1,
  is_mandatory tinyint(1) DEFAULT 0,
  tax_included tinyint(1) DEFAULT 1,
  status varchar(20) DEFAULT 'active',
  sort_order int(11) DEFAULT 0,
  PRIMARY KEY  (id),
  KEY idx_property (property_id)
) {$charset};

CREATE TABLE {$p}guests (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  first_name varchar(100) NOT NULL,
  last_name varchar(100) NOT NULL,
  email varchar(255) DEFAULT '',
  phone varchar(50) DEFAULT '',
  document_type varchar(20) DEFAULT '',
  document_number varchar(50) DEFAULT '',
  country char(2) DEFAULT '',
  city varchar(100) DEFAULT '',
  address varchar(500) DEFAULT '',
  notes text,
  is_blacklisted tinyint(1) DEFAULT 0,
  marketing_opt_out tinyint(1) DEFAULT 0,
  blacklist_reason text,
  wp_user_id bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_email (email),
  KEY idx_document (document_type,document_number),
  KEY idx_name (last_name,first_name),
  KEY idx_phone (phone)
) {$charset};

CREATE TABLE {$p}bookings (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  booking_code varchar(20) NOT NULL,
  property_id bigint(20) unsigned NOT NULL,
  guest_id bigint(20) unsigned NOT NULL,
  rate_plan_id bigint(20) unsigned DEFAULT NULL,
  check_in date NOT NULL,
  check_out date NOT NULL,
  nights smallint(5) unsigned NOT NULL,
  adults tinyint(3) unsigned DEFAULT 1,
  children tinyint(3) unsigned DEFAULT 0,
  status varchar(20) DEFAULT 'pending',
  source varchar(20) DEFAULT 'web',
  source_ref varchar(255) DEFAULT '',
  subtotal decimal(12,2) NOT NULL,
  extras_total decimal(12,2) DEFAULT 0.00,
  taxes_total decimal(12,2) DEFAULT 0.00,
  discount_total decimal(12,2) DEFAULT 0.00,
  grand_total decimal(12,2) NOT NULL,
  amount_paid decimal(12,2) DEFAULT 0.00,
  balance_due decimal(12,2) DEFAULT 0.00,
  payment_status varchar(20) DEFAULT 'unpaid',
  payment_method varchar(30) DEFAULT 'mercadopago',
  currency char(3) DEFAULT 'ARS',
  pricing_snapshot longtext,
  coupon_code varchar(50) DEFAULT '',
  special_requests text,
  internal_notes text,
  access_token varchar(64) NOT NULL,
  cancellation_policy_json longtext,
  cancelled_at datetime DEFAULT NULL,
  cancel_reason text,
  checked_in_at datetime DEFAULT NULL,
  checked_out_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_code (booking_code),
  UNIQUE KEY idx_token (access_token),
  KEY idx_property_dates (property_id,check_in,check_out),
  KEY idx_guest (guest_id),
  KEY idx_status (status),
  KEY idx_source (source),
  KEY idx_created (created_at)
) {$charset};

CREATE TABLE {$p}booking_rooms (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  booking_id bigint(20) unsigned NOT NULL,
  room_type_id bigint(20) unsigned NOT NULL,
  room_unit_id bigint(20) unsigned DEFAULT NULL,
  adults tinyint(3) unsigned DEFAULT 1,
  children tinyint(3) unsigned DEFAULT 0,
  rate_per_night_json longtext,
  subtotal decimal(12,2) NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_booking (booking_id),
  KEY idx_unit (room_unit_id),
  KEY idx_room_type (room_type_id)
) {$charset};

CREATE TABLE {$p}booking_extras (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  booking_id bigint(20) unsigned NOT NULL,
  extra_id bigint(20) unsigned NOT NULL,
  quantity tinyint(3) unsigned DEFAULT 1,
  unit_price decimal(12,2) NOT NULL,
  total_price decimal(12,2) NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_booking (booking_id)
) {$charset};

CREATE TABLE {$p}payments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  booking_id bigint(20) unsigned NOT NULL,
  gateway varchar(50) NOT NULL,
  gateway_txn_id varchar(255) DEFAULT '',
  intent_id varchar(255) DEFAULT '',
  amount decimal(12,2) NOT NULL,
  currency char(3) DEFAULT 'ARS',
  pay_mode varchar(20) DEFAULT 'total',
  type varchar(20) DEFAULT 'full',
  status varchar(20) DEFAULT 'pending',
  gateway_data longtext,
  notes text,
  idempotency_key varchar(100) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_booking (booking_id),
  KEY idx_gateway_txn (gateway,gateway_txn_id),
  KEY idx_status_date (status,created_at),
  UNIQUE KEY idx_intent (gateway,intent_id),
  UNIQUE KEY idx_idempotency (idempotency_key)
) {$charset};

CREATE TABLE {$p}coupons (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(50) NOT NULL,
  type varchar(20) NOT NULL DEFAULT 'percent',
  value decimal(12,2) NOT NULL,
  starts_at datetime DEFAULT NULL,
  ends_at datetime DEFAULT NULL,
  min_nights tinyint(3) unsigned DEFAULT 0,
  room_type_ids text,
  rate_plan_ids text,
  usage_limit_total int(10) unsigned DEFAULT NULL,
  usage_limit_per_email int(10) unsigned DEFAULT NULL,
  stackable tinyint(1) DEFAULT 0,
  applies_to varchar(30) DEFAULT 'room_only',
  active tinyint(1) DEFAULT 1,
  is_automatic tinyint(1) DEFAULT 0,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_code (code)
) {$charset};

CREATE TABLE {$p}coupon_redemptions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  coupon_id bigint(20) unsigned NOT NULL,
  booking_id bigint(20) unsigned NOT NULL,
  email varchar(255) NOT NULL,
  amount_discount decimal(12,2) NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_coupon (coupon_id),
  KEY idx_booking (booking_id),
  KEY idx_email (email)
) {$charset};

CREATE TABLE {$p}locks (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  lock_key varchar(64) NOT NULL,
  property_id bigint(20) unsigned NOT NULL,
  room_type_id bigint(20) unsigned NOT NULL,
  rate_plan_id bigint(20) unsigned DEFAULT NULL,
  check_in date NOT NULL,
  check_out date NOT NULL,
  qty smallint(5) unsigned DEFAULT 1,
  booking_id bigint(20) unsigned DEFAULT NULL,
  expires_at datetime NOT NULL,
  meta_json longtext,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_lock_key (lock_key),
  KEY idx_type_dates (room_type_id,check_in,check_out,expires_at),
  KEY idx_property_expires (property_id,expires_at),
  KEY idx_expires (expires_at),
  KEY idx_booking (booking_id)
) {$charset};

CREATE TABLE {$p}housekeeping_tasks (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  room_unit_id bigint(20) unsigned NOT NULL,
  property_id bigint(20) unsigned NOT NULL,
  task_type varchar(30) DEFAULT 'cleaning',
  status varchar(20) DEFAULT 'pending',
  priority tinyint(3) unsigned DEFAULT 0,
  assigned_to bigint(20) unsigned DEFAULT NULL,
  notes text,
  due_date date DEFAULT NULL,
  completed_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_room (room_unit_id),
  KEY idx_property_date (property_id,due_date),
  KEY idx_status (status)
) {$charset};

CREATE TABLE {$p}email_templates (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  property_id bigint(20) unsigned DEFAULT NULL,
  event_type varchar(50) NOT NULL,
  subject varchar(500) NOT NULL,
  body_html longtext NOT NULL,
  placeholders longtext,
  is_active tinyint(1) DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_property_event (property_id,event_type)
) {$charset};

CREATE TABLE {$p}ical_feeds (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  property_id bigint(20) unsigned NOT NULL,
  room_unit_id bigint(20) unsigned DEFAULT NULL,
  name varchar(100) DEFAULT 'custom',
  url varchar(1000) DEFAULT '',
  export_token varchar(64) DEFAULT '',
  conflict_policy varchar(20) DEFAULT 'mark_conflict',
  sync_interval smallint(5) unsigned DEFAULT 15,
  last_sync_at datetime DEFAULT NULL,
  last_sync_status varchar(20) DEFAULT 'ok',
  last_error text,
  is_active tinyint(1) DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_unit (room_unit_id),
  KEY idx_active (is_active)
) {$charset};

CREATE TABLE {$p}ical_events (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  feed_id bigint(20) unsigned NOT NULL,
  external_uid varchar(255) NOT NULL,
  booking_id bigint(20) unsigned DEFAULT NULL,
  start_date date NOT NULL,
  end_date date NOT NULL,
  summary varchar(255) DEFAULT '',
  description text,
  event_hash char(32) NOT NULL,
  last_seen_at datetime NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_feed_uid (feed_id,external_uid),
  KEY idx_dates (start_date,end_date),
  KEY idx_booking (booking_id)
) {$charset};

CREATE TABLE {$p}conflicts (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  property_id bigint(20) unsigned NOT NULL,
  room_unit_id bigint(20) unsigned NOT NULL,
  local_booking_id bigint(20) unsigned DEFAULT NULL,
  ical_event_id bigint(20) unsigned DEFAULT NULL,
  start_date date NOT NULL,
  end_date date NOT NULL,
  type varchar(20) NOT NULL,
  resolved tinyint(1) DEFAULT 0,
  resolved_at datetime DEFAULT NULL,
  resolved_note text,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_unit_dates (room_unit_id,start_date,end_date),
  KEY idx_resolved (resolved)
) {$charset};

CREATE TABLE {$p}audit_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_type varchar(50) NOT NULL,
  severity varchar(20) DEFAULT 'info',
  entity_type varchar(50) DEFAULT '',
  entity_id bigint(20) unsigned DEFAULT NULL,
  user_id bigint(20) unsigned DEFAULT 0,
  ip_address varchar(45) DEFAULT '',
  message text NOT NULL,
  context_json longtext,
  action varchar(50) DEFAULT '',
  before_json longtext,
  after_json longtext,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_event (event_type),
  KEY idx_entity (entity_type,entity_id),
  KEY idx_created (created_at),
  KEY idx_severity (severity),
  KEY idx_action (action)
) {$charset};

CREATE TABLE {$p}settings (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  property_id bigint(20) unsigned NOT NULL,
  setting_key varchar(100) NOT NULL,
  setting_value longtext,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_prop_key (property_id,setting_key)
) {$charset};

CREATE TABLE {$p}promotions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  rule_type enum('percent', 'fixed', 'stay_pay') NOT NULL DEFAULT 'percent',
  rule_value varchar(50) NOT NULL,
  min_nights int(11) NOT NULL DEFAULT 0,
  starts_at date DEFAULT NULL,
  ends_at date DEFAULT NULL,
  property_id bigint(20) unsigned DEFAULT NULL,
  room_type_ids text DEFAULT NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY property_id (property_id)
) {$charset};
";
    }

    /**
     * List of all table names (without WP prefix).
     */
    public static function table_names(): array {
        return [
            'properties', 'room_types', 'room_units', 'rate_plans',
            'rates', 'daily_rates', 'extras', 'guests', 'bookings',
            'booking_rooms', 'booking_extras', 'payments', 'coupons', 'locks',
            'housekeeping_tasks', 'email_templates', 'ical_feeds', 'ical_events', 'conflicts', 'audit_log', 'settings', 'promotions',
        ];
    }

    /**
     * Full table name with wp prefix.
     */
    public static function table( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . ARTECHIA_PMS_PREFIX . $name;
    }
}
