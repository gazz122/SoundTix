# SoundTix CDM (Conceptual Data Model)

> Detail fisiknya — tipe kolom, nullability, default, index, dan foreign key — ada di [PDM](./PDM.md).

## Entities and Relationships

### Core Entities
- users (id, name, email, email_verified_at, is_verified, password, phone, role, is_active, remember_token, otp_hash, otp_expires_at, deleted_at, timestamps)
- events (id, organizer_id, title, slug, description, banner_path, venue_name, city, address, event_date, duration_type, total_days, start_time, end_time, open_gate, close_gate, status, rejection_reason, cancellation_reason, timestamps)
- event_days (id, event_id, day_number, date, open_gate, close_gate, timestamps)
- event_rundowns (id, event_id, performer_id, event_day_id, title, description, start_time, end_time, timestamps)
- performers (id, name, genre, image_path, timestamps)
- event_performers (event_id, performer_id)
- ticket_categories (id, event_id, name, access_type, event_day_id, description, price, total_quantity, available_quantity, max_per_order, timestamps)
- orders (id, order_code, customer_id, total_amount, admin_fee, grand_total, status, expires_at, paid_at, timestamps)
- order_items (id, order_id, ticket_category_id, quantity, price_per_unit, subtotal, timestamps)
- payments (id, order_id, transaction_id, midtrans_transaction_id, payment_type, gross_amount, payment_status, raw_response, timestamps)
- tickets (id, order_item_id, ticket_code, qr_payload, status, used_at, validated_by, timestamps)
- ticket_scans (id, ticket_id, event_day_id, scanned_at, gatekeeper_id, timestamps)
- refunds (id, order_id, payment_id, amount, bank_name, account_number, account_holder_name, proof_of_transfer, refund_key, refund_gateway, gateway_reference_id, status, failure_reason, gateway_response, processed_at, reason, notes, timestamps)
- payouts (id, event_id, organizer_id, gross_amount, platform_fee, refund_deductions, net_amount, bank_name, account_number, account_holder_name, status, transfer_proof, admin_notes, processed_at, processed_by, timestamps)
- banners (id, title, image_path, link_url, is_active, order, timestamps)
- faqs (id, category_id, question, answer, is_published, order, timestamps)
- faq_categories (id, name, slug, icon, order, timestamps)
- saved_events (id, user_id, event_id, timestamps)
- notifications (id, type, notifiable_type, notifiable_id, data, read_at, timestamps)

## Relationships
- User (organizer) hasMany Event, hasMany Payout
- User (customer) hasMany Order
- User hasMany SavedEvent, belongsToMany Event through saved_events
- Event belongsTo Organizer (User), hasMany EventDay, EventRundown, TicketCategory, Payout
- Event belongsToMany Performer through event_performers
- Event belongsToMany User through saved_events (savedByUsers)
- EventDay belongsTo Event, hasMany TicketCategory, hasMany TicketScan
- EventRundown belongsTo Event, belongsTo Performer (nullable), belongsTo EventDay (nullable)
- TicketCategory belongsTo Event, belongsTo EventDay (nullable), hasMany OrderItem
- Order belongsTo Customer (User), hasMany OrderItem, hasMany Payment, hasMany Refund
- OrderItem belongsTo Order, belongsTo TicketCategory, hasMany Ticket
- Payment belongsTo Order, hasMany Refund
- Ticket belongsTo OrderItem, belongsTo User (validated_by, nullable), hasMany TicketScan
- TicketScan belongsTo Ticket, belongsTo EventDay, belongsTo User (gatekeeper, nullable)
- Refund belongsTo Order, belongsTo Payment (nullable)
- Payout belongsTo Event, belongsTo Organizer (User), belongsTo User (processed_by, nullable)
- FAQ belongsTo FAQCategory, FAQCategory hasMany FAQ
- SavedEvent belongsTo User, belongsTo Event
- Notification belongsTo User (polymorphic via notifiable)
- Banner has no relation to other entities

## Enums

### Application enums (app/Enums)
- UserRole: admin, organizer, customer, gatekeeper
- EventStatus: draft, pending, published, rejected, pending_cancellation, cancelled, completed
- OrderStatus: pending, paid, expired, cancelled, failed
- PaymentStatus: pending, settlement, expire, failure
- PaymentType: bank_transfer, qris
- TicketStatus: active, used, expired, cancelled
- RefundStatus: pending, waiting_bank_details, processing, completed, failed, refunded
- RefundGateway: midtrans_refund_api, midtrans_iris_payout, manual
- PayoutStatus: pending, approved, processing, completed, failed
- Bank: bca, mandiri, bni, bri, permata, cimb

### Database-level enums
- events.duration_type: single, multi_day
- ticket_categories.access_type: ALL_DAYS, SINGLE_DAY

## Unique Constraints
- users.email
- events.slug
- event_days (event_id, day_number)
- orders.order_code
- payments.transaction_id
- payments.midtrans_transaction_id
- tickets.ticket_code
- ticket_scans (ticket_id, event_day_id)
- refunds.refund_key
- saved_events (user_id, event_id)
- faq_categories.slug

## Framework Tables
Laravel and package internals, deliberately not part of the domain model:
migrations, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, password_reset_tokens, personal_access_tokens

## Notes
- Only users uses soft deletes (deleted_at). events and orders have no deleted_at column.
- faqs publishes through the boolean is_published; there is no FAQStatus enum.
- A refund has no direct event_id. It reaches an event through order -> order_item -> ticket_category -> event, so there is no Event hasMany Refund relationship.
- A rundown session points to at most one performer and at most one event day. A session without a performer keeps its own title; the "title or performer" rule is enforced in the controller, not the database.
- Rundown edits are live: buyers open the "QR Rundown Acara" from "Tiket Saya" and scan it with a phone camera. The QR points to the static `public/rundown-viewer.html`, with the whole rundown snapshot base64-encoded in the URL fragment, so the viewer needs no Laravel route or login. A signed `rundown.{order_id}.{event_id}.{hmac}` payload is still available for the in-app scanner. The public event page no longer shows the rundown.
