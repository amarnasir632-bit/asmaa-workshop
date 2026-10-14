-- Generated from the Laravel SQLite migrations; keep in sync with database/migrations.

CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "username" varchar not null, "email" varchar, "password" varchar not null, "is_active" tinyint(1) not null default '1', "last_login_at" datetime, "remember_token" varchar, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "roles" ("id" integer primary key autoincrement not null, "name" varchar not null, "label" varchar not null, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "permissions" ("id" integer primary key autoincrement not null, "name" varchar not null, "label" varchar not null);

CREATE TABLE "user_roles" ("user_id" integer not null, "role_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("role_id") references "roles"("id") on delete restrict, primary key ("user_id", "role_id"));

CREATE TABLE "role_permissions" ("role_id" integer not null, "permission_id" integer not null, foreign key("role_id") references "roles"("id") on delete cascade, foreign key("permission_id") references "permissions"("id") on delete cascade, primary key ("role_id", "permission_id"));

CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));

CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, foreign key("user_id") references "users"("id") on delete set null, primary key ("id"));

CREATE TABLE "guardians" ("id" integer primary key autoincrement not null, "name" varchar not null, "phone" varchar not null, "alternate_phone" varchar, "notes" text, "deleted_at" datetime, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "schools" ("id" integer primary key autoincrement not null, "name" varchar not null, "phone" varchar, "address" text, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "grades" ("id" integer primary key autoincrement not null, "school_id" integer not null, "name" varchar not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("school_id") references "schools"("id") on delete restrict);

CREATE TABLE "children" ("id" integer primary key autoincrement not null, "guardian_id" integer not null, "school_id" integer, "grade_id" integer, "name" varchar not null, "notes" text, "deleted_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("guardian_id") references "guardians"("id") on delete restrict, foreign key("school_id") references "schools"("id") on delete set null, foreign key("grade_id") references "grades"("id") on delete set null);

CREATE TABLE "current_measurements" ("id" integer primary key autoincrement not null, "child_id" integer not null, "shoulder_cm" numeric, "chest_cm" numeric, "sleeve_cm" numeric, "waist_circumference_cm" numeric, "length_cm" numeric, "updated_by" integer, "created_at" datetime, "updated_at" datetime, foreign key("child_id") references "children"("id") on delete cascade, foreign key("updated_by") references "users"("id") on delete set null);

CREATE TABLE "garment_types" ("id" integer primary key autoincrement not null, "name" varchar not null, "requires_size" tinyint(1) not null default '1', "requires_color" tinyint(1) not null default '0', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "inventory_variants" ("id" integer primary key autoincrement not null, "school_id" integer, "garment_type_id" integer not null, "grade_id" integer, "size" varchar, "color" varchar, "sku" varchar, "attributes_key" varchar not null, "description" varchar, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("school_id") references "schools"("id") on delete restrict, foreign key("garment_type_id") references "garment_types"("id") on delete restrict, foreign key("grade_id") references "grades"("id") on delete set null);

CREATE TABLE "orders" ("id" integer primary key autoincrement not null, "number" varchar not null, "type" varchar check ("type" in ('custom', 'ready', 'alteration')) not null, "guardian_id" integer, "customer_name_snapshot" varchar, "created_by" integer not null, "created_on" date not null, "due_on" date, "operational_status" varchar check ("operational_status" in ('new', 'in_progress', 'partially_received', 'fully_received', 'ready', 'delivered', 'cancelled')) not null default 'new', "notes" text, "items_total" numeric not null default '0', "discount_total" numeric not null default '0', "approved_total" numeric not null default '0', "cancelled_at" datetime, "deleted_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("guardian_id") references "guardians"("id") on delete restrict, foreign key("created_by") references "users"("id") on delete restrict);

CREATE TABLE "school_contracts" ("id" integer primary key autoincrement not null, "order_id" integer not null, "school_id" integer not null, "contract_number" varchar, "contact_name" varchar, "terms" text, "created_at" datetime, "updated_at" datetime, foreign key("order_id") references "orders"("id") on delete cascade, foreign key("school_id") references "schools"("id") on delete restrict);

CREATE TABLE "beneficiaries" ("id" integer primary key autoincrement not null, "school_contract_id" integer not null, "child_id" integer, "name_snapshot" varchar not null, "grade_snapshot" varchar, "source_reference" varchar, "created_at" datetime, "updated_at" datetime, foreign key("school_contract_id") references "school_contracts"("id") on delete cascade, foreign key("child_id") references "children"("id") on delete set null);

CREATE TABLE "order_items" ("id" integer primary key autoincrement not null, "order_id" integer not null, "beneficiary_id" integer, "child_id" integer, "inventory_variant_id" integer, "garment_type_id" integer, "description_snapshot" varchar not null, "quantity" integer not null, "unit_price" numeric not null, "discount_amount" numeric not null default '0', "line_total" numeric not null, "created_at" datetime, "updated_at" datetime, foreign key("order_id") references "orders"("id") on delete cascade, foreign key("beneficiary_id") references "beneficiaries"("id") on delete set null, foreign key("child_id") references "children"("id") on delete restrict, foreign key("inventory_variant_id") references "inventory_variants"("id") on delete restrict, foreign key("garment_type_id") references "garment_types"("id") on delete set null);

CREATE TABLE "measurement_snapshots" ("id" integer primary key autoincrement not null, "order_item_id" integer not null, "shoulder_cm" numeric, "chest_cm" numeric, "sleeve_cm" numeric, "waist_circumference_cm" numeric, "length_cm" numeric, "created_at" datetime, "updated_at" datetime, foreign key("order_item_id") references "order_items"("id") on delete cascade);

CREATE TABLE "production_jobs" ("id" integer primary key autoincrement not null, "order_id" integer not null, "order_item_id" integer not null, "quantity" integer not null, "stage" varchar check ("stage" in ('not_started', 'sewing', 'with_tailor', 'waiting_completion', 'completed')) not null default 'not_started', "expected_on" date, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("order_id") references "orders"("id") on delete restrict, foreign key("order_item_id") references "order_items"("id") on delete restrict);

CREATE TABLE "tailor_dispatches" ("id" integer primary key autoincrement not null, "production_job_id" integer not null, "recipient_name" varchar not null, "sent_on" date not null, "notes" text, "recorded_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("production_job_id") references "production_jobs"("id") on delete restrict, foreign key("recorded_by") references "users"("id") on delete restrict);

CREATE TABLE "tailor_dispatch_items" ("id" integer primary key autoincrement not null, "tailor_dispatch_id" integer not null, "order_item_id" integer not null, "quantity_sent" integer not null, "agreed_cost" numeric, "created_at" datetime, "updated_at" datetime, foreign key("tailor_dispatch_id") references "tailor_dispatches"("id") on delete cascade, foreign key("order_item_id") references "order_items"("id") on delete restrict);

CREATE TABLE "tailor_receipts" ("id" integer primary key autoincrement not null, "tailor_dispatch_id" integer not null, "received_on" date not null, "notes" text, "recorded_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("tailor_dispatch_id") references "tailor_dispatches"("id") on delete restrict, foreign key("recorded_by") references "users"("id") on delete restrict);

CREATE TABLE "tailor_receipt_items" ("id" integer primary key autoincrement not null, "tailor_receipt_id" integer not null, "tailor_dispatch_item_id" integer not null, "quantity_received" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("tailor_receipt_id") references "tailor_receipts"("id") on delete cascade, foreign key("tailor_dispatch_item_id") references "tailor_dispatch_items"("id") on delete restrict);

CREATE TABLE "deliveries" ("id" integer primary key autoincrement not null, "order_id" integer not null, "delivered_at" datetime not null, "received_by_name" varchar not null, "receipt_number" varchar, "recorded_by" integer not null, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("order_id") references "orders"("id") on delete restrict, foreign key("recorded_by") references "users"("id") on delete restrict);

CREATE TABLE "delivery_items" ("id" integer primary key autoincrement not null, "delivery_id" integer not null, "order_item_id" integer not null, "quantity_delivered" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("delivery_id") references "deliveries"("id") on delete cascade, foreign key("order_item_id") references "order_items"("id") on delete restrict);

CREATE TABLE "payments" ("id" integer primary key autoincrement not null, "order_id" integer not null, "amount" numeric not null, "paid_at" datetime not null, "method" varchar check ("method" in ('cash', 'bank_transfer')) not null, "verification_status" varchar check ("verification_status" in ('not_required', 'pending', 'verified')) not null default 'not_required', "transaction_reference" varchar, "recorded_by" integer not null, "verified_by" integer, "verified_at" datetime, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("order_id") references "orders"("id") on delete restrict, foreign key("recorded_by") references "users"("id") on delete restrict, foreign key("verified_by") references "users"("id") on delete restrict);

CREATE TABLE "payment_evidence" ("id" integer primary key autoincrement not null, "payment_id" integer not null, "private_path" varchar not null, "original_name" varchar not null, "mime_type" varchar not null, "size_bytes" integer not null, "sha256" varchar not null, "uploaded_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("payment_id") references "payments"("id") on delete restrict, foreign key("uploaded_by") references "users"("id") on delete restrict);

CREATE TABLE "cancellations" ("id" integer primary key autoincrement not null, "order_id" integer not null, "production_stage_snapshot" varchar not null, "reason" text not null, "cancellation_discount" numeric not null, "refund_due" numeric not null default '0', "approved_by" integer not null, "approved_at" datetime not null, "created_at" datetime, "updated_at" datetime, foreign key("order_id") references "orders"("id") on delete restrict, foreign key("approved_by") references "users"("id") on delete restrict);

CREATE TABLE "expense_categories" ("id" integer primary key autoincrement not null, "name" varchar not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "expenses" ("id" integer primary key autoincrement not null, "expense_category_id" integer not null, "amount" numeric not null, "spent_on" date not null, "description" varchar not null, "notes" text, "recorded_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("expense_category_id") references "expense_categories"("id") on delete restrict, foreign key("recorded_by") references "users"("id") on delete restrict);

CREATE TABLE "opening_inventory_batches" ("id" integer primary key autoincrement not null, "reference" varchar not null, "counted_on" date not null, "status" varchar check ("status" in ('draft', 'approved')) not null default 'draft', "created_by" integer not null, "approved_by" integer, "approved_at" datetime, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("created_by") references "users"("id") on delete restrict, foreign key("approved_by") references "users"("id") on delete restrict);

CREATE TABLE "opening_inventory_entries" ("id" integer primary key autoincrement not null, "opening_inventory_batch_id" integer not null, "inventory_variant_id" integer not null, "quantity_counted" integer not null, "previous_quantity" integer, "difference_reason" text, "created_at" datetime, "updated_at" datetime, foreign key("opening_inventory_batch_id") references "opening_inventory_batches"("id") on delete cascade, foreign key("inventory_variant_id") references "inventory_variants"("id") on delete restrict);

CREATE TABLE "inventory_adjustments" ("id" integer primary key autoincrement not null, "reference" varchar not null, "status" varchar check ("status" in ('pending', 'approved', 'rejected')) not null default 'pending', "reason" text not null, "created_by" integer not null, "approved_by" integer, "approved_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("created_by") references "users"("id") on delete restrict, foreign key("approved_by") references "users"("id") on delete restrict);

CREATE TABLE "inventory_adjustment_items" ("id" integer primary key autoincrement not null, "inventory_adjustment_id" integer not null, "inventory_variant_id" integer not null, "quantity_delta" integer not null, "reason" text not null, "created_at" datetime, "updated_at" datetime, foreign key("inventory_adjustment_id") references "inventory_adjustments"("id") on delete cascade, foreign key("inventory_variant_id") references "inventory_variants"("id") on delete restrict);

CREATE TABLE "inventory_balances" ("id" integer primary key autoincrement not null, "inventory_variant_id" integer not null, "quantity_available" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("inventory_variant_id") references "inventory_variants"("id") on delete restrict);

CREATE TABLE "inventory_movements" ("id" integer primary key autoincrement not null, "inventory_variant_id" integer not null, "quantity_change" integer not null, "movement_type" varchar check ("movement_type" in ('opening', 'ready_sale', 'ready_sale_cancel', 'customer_return', 'adjustment_in', 'adjustment_out')) not null, "order_item_id" integer, "delivery_item_id" integer, "opening_inventory_entry_id" integer, "inventory_adjustment_item_id" integer, "reason" text, "occurred_at" datetime not null, "recorded_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("inventory_variant_id") references "inventory_variants"("id") on delete restrict, foreign key("order_item_id") references "order_items"("id") on delete restrict, foreign key("delivery_item_id") references "delivery_items"("id") on delete restrict, foreign key("opening_inventory_entry_id") references "opening_inventory_entries"("id") on delete restrict, foreign key("inventory_adjustment_item_id") references "inventory_adjustment_items"("id") on delete restrict, foreign key("recorded_by") references "users"("id") on delete restrict);

CREATE TABLE "audit_logs" ("id" integer primary key autoincrement not null, "user_id" integer, "action" varchar not null, "subject_type" varchar not null, "subject_id" integer, "before_values" text, "after_values" text, "reason" text, "request_id" varchar, "occurred_at" datetime not null, foreign key("user_id") references "users"("id") on delete set null);

CREATE TABLE "notifications" ("id" integer primary key autoincrement not null, "user_id" integer not null, "type" varchar not null, "title" varchar not null, "body" text, "subject_type" varchar, "subject_id" integer, "read_at" datetime, "created_at" datetime, "updated_at" datetime, "resolved_at" datetime, foreign key("user_id") references "users"("id") on delete cascade);

CREATE TABLE "historical_import_batches" ("id" integer primary key autoincrement not null, "source_description" varchar not null, "imported_on" date not null, "imported_by" integer not null, "records_seen" integer not null default '0', "records_imported" integer not null default '0', "records_flagged" integer not null default '0', "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("imported_by") references "users"("id") on delete restrict);

CREATE TABLE "historical_record_issues" ("id" integer primary key autoincrement not null, "historical_import_batch_id" integer not null, "record_type" varchar not null, "source_reference" varchar, "issue_type" varchar check ("issue_type" in ('missing', 'conflicting', 'unverified', 'duplicate_candidate')) not null, "description" text not null, "resolution" text, "resolved_by" integer, "resolved_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("historical_import_batch_id") references "historical_import_batches"("id") on delete cascade, foreign key("resolved_by") references "users"("id") on delete restrict);

CREATE TABLE "refunds" ("id" integer primary key autoincrement not null, "order_id" integer not null, "original_payment_id" integer, "returned_delivery_item_id" integer, "approved_amount" numeric not null, "refunded_amount" numeric not null default ('0'), "method" varchar, "refunded_at" datetime, "approved_by" integer not null, "approved_at" datetime not null, "reason" text not null, "created_at" datetime, "updated_at" datetime, "cancellation_id" integer, "returned_item_received_at" datetime, "returned_quantity" integer, "returned_item_condition" varchar check ("returned_item_condition" in ('resalable', 'damaged', 'altered')), "inspection_notes" text, foreign key("approved_by") references users("id") on delete restrict on update no action, foreign key("returned_delivery_item_id") references delivery_items("id") on delete restrict on update no action, foreign key("original_payment_id") references payments("id") on delete restrict on update no action, foreign key("order_id") references orders("id") on delete restrict on update no action, foreign key("cancellation_id") references "cancellations"("id") on delete restrict);

CREATE TABLE "refund_transactions" ("id" integer primary key autoincrement not null, "refund_id" integer not null, "amount" numeric not null, "method" varchar check ("method" in ('cash', 'bank_transfer')) not null, "refunded_at" datetime not null, "recorded_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("refund_id") references "refunds"("id") on delete restrict, foreign key("recorded_by") references "users"("id") on delete restrict);

CREATE TABLE "financial_corrections" ("id" integer primary key autoincrement not null, "order_id" integer not null, "subject_type" varchar not null, "subject_id" integer not null, "field_name" varchar not null, "old_amount" numeric not null, "new_amount" numeric not null, "reason" text not null, "approved_by" integer not null, "occurred_at" datetime not null, "created_at" datetime, "updated_at" datetime, foreign key("order_id") references "orders"("id") on delete restrict, foreign key("approved_by") references "users"("id") on delete restrict);

CREATE TABLE "historical_import_records" ("id" integer primary key autoincrement not null, "historical_import_batch_id" integer not null, "record_type" varchar not null, "source_reference" varchar, "original_date" date, "transcription" text not null, "fingerprint" varchar not null, "confidence" varchar check ("confidence" in ('confirmed', 'incomplete', 'conflicting', 'unverified')) not null default 'unverified', "review_status" varchar check ("review_status" in ('pending', 'reviewed', 'needs_correction', 'rejected')) not null default 'pending', "entered_by" integer not null, "reviewed_by" integer, "reviewed_at" datetime, "review_notes" text, "created_at" datetime, "updated_at" datetime, foreign key("historical_import_batch_id") references "historical_import_batches"("id") on delete restrict, foreign key("entered_by") references "users"("id") on delete restrict, foreign key("reviewed_by") references "users"("id") on delete restrict);

CREATE TABLE "historical_record_revisions" ("id" integer primary key autoincrement not null, "historical_import_record_id" integer not null, "previous_transcription" text not null, "corrected_transcription" text not null, "reason" text not null, "corrected_by" integer not null, "corrected_at" datetime not null, foreign key("historical_import_record_id") references "historical_import_records"("id") on delete restrict, foreign key("corrected_by") references "users"("id") on delete restrict);

CREATE UNIQUE INDEX "users_username_unique" on "users" ("username");

CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

CREATE INDEX "users_is_active_index" on "users" ("is_active");

CREATE UNIQUE INDEX "roles_name_unique" on "roles" ("name");

CREATE UNIQUE INDEX "permissions_name_unique" on "permissions" ("name");

CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");

CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");

CREATE INDEX "guardians_name_index" on "guardians" ("name");

CREATE INDEX "guardians_phone_index" on "guardians" ("phone");

CREATE UNIQUE INDEX "schools_name_unique" on "schools" ("name");

CREATE INDEX "schools_is_active_index" on "schools" ("is_active");

CREATE UNIQUE INDEX "grades_school_id_name_unique" on "grades" ("school_id", "name");

CREATE INDEX "children_guardian_id_name_index" on "children" ("guardian_id", "name");

CREATE INDEX "children_name_index" on "children" ("name");

CREATE UNIQUE INDEX "current_measurements_child_id_unique" on "current_measurements" ("child_id");

CREATE UNIQUE INDEX "garment_types_name_unique" on "garment_types" ("name");

CREATE INDEX "garment_types_is_active_index" on "garment_types" ("is_active");

CREATE INDEX "inventory_variant_lookup" on "inventory_variants" ("school_id", "garment_type_id", "size", "color");

CREATE UNIQUE INDEX "inventory_variants_sku_unique" on "inventory_variants" ("sku");

CREATE UNIQUE INDEX "inventory_variants_attributes_key_unique" on "inventory_variants" ("attributes_key");

CREATE INDEX "inventory_variants_is_active_index" on "inventory_variants" ("is_active");

CREATE UNIQUE INDEX "orders_number_unique" on "orders" ("number");

CREATE INDEX "orders_created_on_index" on "orders" ("created_on");

CREATE INDEX "orders_due_on_index" on "orders" ("due_on");

CREATE INDEX "orders_operational_status_index" on "orders" ("operational_status");

CREATE UNIQUE INDEX "school_contracts_order_id_unique" on "school_contracts" ("order_id");

CREATE UNIQUE INDEX "school_contracts_contract_number_unique" on "school_contracts" ("contract_number");

CREATE INDEX "beneficiaries_school_contract_id_name_snapshot_index" on "beneficiaries" ("school_contract_id", "name_snapshot");

CREATE INDEX "order_items_order_id_beneficiary_id_index" on "order_items" ("order_id", "beneficiary_id");

CREATE UNIQUE INDEX "measurement_snapshots_order_item_id_unique" on "measurement_snapshots" ("order_item_id");

CREATE UNIQUE INDEX "production_jobs_order_item_id_unique" on "production_jobs" ("order_item_id");

CREATE INDEX "production_jobs_stage_index" on "production_jobs" ("stage");

CREATE INDEX "tailor_dispatches_sent_on_index" on "tailor_dispatches" ("sent_on");

CREATE INDEX "tailor_receipts_received_on_index" on "tailor_receipts" ("received_on");

CREATE INDEX "deliveries_delivered_at_index" on "deliveries" ("delivered_at");

CREATE UNIQUE INDEX "deliveries_receipt_number_unique" on "deliveries" ("receipt_number");

CREATE UNIQUE INDEX "delivery_items_delivery_id_order_item_id_unique" on "delivery_items" ("delivery_id", "order_item_id");

CREATE INDEX "payments_order_id_paid_at_index" on "payments" ("order_id", "paid_at");

CREATE INDEX "payments_paid_at_index" on "payments" ("paid_at");

CREATE INDEX "payments_verification_status_index" on "payments" ("verification_status");

CREATE INDEX "payments_transaction_reference_index" on "payments" ("transaction_reference");

CREATE UNIQUE INDEX "payment_evidence_payment_id_sha256_unique" on "payment_evidence" ("payment_id", "sha256");

CREATE UNIQUE INDEX "payment_evidence_private_path_unique" on "payment_evidence" ("private_path");

CREATE INDEX "payment_evidence_sha256_index" on "payment_evidence" ("sha256");

CREATE INDEX "cancellations_approved_at_index" on "cancellations" ("approved_at");

CREATE UNIQUE INDEX "expense_categories_name_unique" on "expense_categories" ("name");

CREATE INDEX "expenses_spent_on_index" on "expenses" ("spent_on");

CREATE UNIQUE INDEX "opening_inventory_batches_reference_unique" on "opening_inventory_batches" ("reference");

CREATE INDEX "opening_inventory_batches_status_index" on "opening_inventory_batches" ("status");

CREATE UNIQUE INDEX "opening_inventory_entries_opening_inventory_batch_id_inventory_variant_id_unique" on "opening_inventory_entries" ("opening_inventory_batch_id", "inventory_variant_id");

CREATE UNIQUE INDEX "inventory_adjustments_reference_unique" on "inventory_adjustments" ("reference");

CREATE INDEX "inventory_adjustments_status_index" on "inventory_adjustments" ("status");

CREATE UNIQUE INDEX "inventory_balances_inventory_variant_id_unique" on "inventory_balances" ("inventory_variant_id");

CREATE INDEX "inventory_movements_inventory_variant_id_occurred_at_index" on "inventory_movements" ("inventory_variant_id", "occurred_at");

CREATE INDEX "inventory_movements_occurred_at_index" on "inventory_movements" ("occurred_at");

CREATE INDEX "audit_logs_subject_type_subject_id_index" on "audit_logs" ("subject_type", "subject_id");

CREATE INDEX "audit_logs_action_index" on "audit_logs" ("action");

CREATE INDEX "audit_logs_subject_id_index" on "audit_logs" ("subject_id");

CREATE INDEX "audit_logs_request_id_index" on "audit_logs" ("request_id");

CREATE INDEX "audit_logs_occurred_at_index" on "audit_logs" ("occurred_at");

CREATE INDEX "notifications_type_index" on "notifications" ("type");

CREATE INDEX "notifications_read_at_index" on "notifications" ("read_at");

CREATE UNIQUE INDEX "payments_order_reference_unique" on "payments" ("order_id", "transaction_reference");

CREATE INDEX "refunds_refunded_at_index" on "refunds" ("refunded_at");

CREATE INDEX "refund_transactions_refunded_at_index" on "refund_transactions" ("refunded_at");

CREATE INDEX "financial_corrections_subject_type_subject_id_index" on "financial_corrections" ("subject_type", "subject_id");

CREATE INDEX "financial_corrections_occurred_at_index" on "financial_corrections" ("occurred_at");

CREATE UNIQUE INDEX "notifications_subject_unique" on "notifications" ("user_id", "type", "subject_type", "subject_id");

CREATE INDEX "notifications_resolved_at_index" on "notifications" ("resolved_at");

CREATE INDEX "historical_import_records_historical_import_batch_id_record_type_index" on "historical_import_records" ("historical_import_batch_id", "record_type");

CREATE INDEX "historical_import_records_review_status_confidence_index" on "historical_import_records" ("review_status", "confidence");

CREATE UNIQUE INDEX "historical_import_records_fingerprint_unique" on "historical_import_records" ("fingerprint");
