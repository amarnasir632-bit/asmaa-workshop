CREATE UNIQUE INDEX inventory_movements_opening_entry_unique
    ON inventory_movements (opening_inventory_entry_id)
    WHERE opening_inventory_entry_id IS NOT NULL;

CREATE UNIQUE INDEX inventory_movements_adjustment_item_unique
    ON inventory_movements (inventory_adjustment_item_id)
    WHERE inventory_adjustment_item_id IS NOT NULL;

CREATE TRIGGER validate_production_job_insert
BEFORE INSERT ON production_jobs
WHEN NEW.quantity < 1
  OR NEW.quantity != (SELECT quantity FROM order_items WHERE id = NEW.order_item_id)
  OR NOT EXISTS (
      SELECT 1 FROM orders
      WHERE orders.id = NEW.order_id
        AND orders.operational_status NOT IN ('cancelled', 'delivered')
        AND orders.type IN ('custom', 'alteration')
  )
BEGIN
    SELECT RAISE(ABORT, 'invalid production job');
END;

CREATE TRIGGER prevent_overdispatch
BEFORE INSERT ON tailor_dispatch_items
WHEN NEW.quantity_sent < 1
  OR NEW.quantity_sent + coalesce((
      SELECT sum(existing.quantity_sent)
      FROM tailor_dispatch_items AS existing
      WHERE existing.order_item_id = NEW.order_item_id
  ), 0) > coalesce((
      SELECT production_jobs.quantity
      FROM production_jobs
      WHERE production_jobs.order_item_id = NEW.order_item_id
  ), 0)
  OR NOT EXISTS (
      SELECT 1
      FROM tailor_dispatches
      JOIN production_jobs ON production_jobs.id = tailor_dispatches.production_job_id
      JOIN orders ON orders.id = production_jobs.order_id
      WHERE tailor_dispatches.id = NEW.tailor_dispatch_id
        AND production_jobs.order_item_id = NEW.order_item_id
        AND orders.operational_status NOT IN ('cancelled', 'delivered')
  )
BEGIN
    SELECT RAISE(ABORT, 'tailor dispatch exceeds remaining production quantity');
END;

CREATE TRIGGER prevent_overreceipt
BEFORE INSERT ON tailor_receipt_items
WHEN NEW.quantity_received < 1
  OR NEW.quantity_received + coalesce((
      SELECT sum(existing.quantity_received)
      FROM tailor_receipt_items AS existing
      WHERE existing.tailor_dispatch_item_id = NEW.tailor_dispatch_item_id
  ), 0) > coalesce((
      SELECT dispatch_items.quantity_sent
      FROM tailor_dispatch_items AS dispatch_items
      WHERE dispatch_items.id = NEW.tailor_dispatch_item_id
  ), 0)
  OR NOT EXISTS (
      SELECT 1
      FROM tailor_receipts
      JOIN tailor_dispatches ON tailor_dispatches.id = tailor_receipts.tailor_dispatch_id
      JOIN tailor_dispatch_items ON tailor_dispatch_items.tailor_dispatch_id = tailor_dispatches.id
      WHERE tailor_receipts.id = NEW.tailor_receipt_id
        AND tailor_dispatch_items.id = NEW.tailor_dispatch_item_id
  )
BEGIN
    SELECT RAISE(ABORT, 'tailor receipt exceeds dispatched quantity');
END;
