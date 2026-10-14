CREATE TRIGGER validate_refund_update
BEFORE UPDATE OF approved_amount, refunded_amount, original_payment_id, cancellation_id,
    returned_delivery_item_id, returned_quantity, returned_item_condition ON refunds
WHEN NEW.approved_amount <= 0
  OR NEW.refunded_amount < OLD.refunded_amount
  OR NEW.refunded_amount > NEW.approved_amount
  OR (NEW.original_payment_id IS NOT NULL AND NOT EXISTS (
      SELECT 1 FROM payments
      WHERE payments.id = NEW.original_payment_id
        AND payments.order_id = NEW.order_id
        AND (payments.method = 'cash' OR payments.verification_status = 'verified')
  ))
  OR (NEW.cancellation_id IS NOT NULL AND NOT EXISTS (
      SELECT 1 FROM cancellations
      WHERE cancellations.id = NEW.cancellation_id AND cancellations.order_id = NEW.order_id
  ))
  OR ROUND(NEW.approved_amount * 100) + coalesce((
      SELECT sum(ROUND(refunds.approved_amount * 100))
      FROM refunds
      WHERE refunds.order_id = NEW.order_id AND refunds.id != OLD.id
  ), 0) > coalesce((
      SELECT sum(ROUND(payments.amount * 100))
      FROM payments
      WHERE payments.order_id = NEW.order_id
        AND (payments.method = 'cash' OR payments.verification_status = 'verified')
  ), 0)
  OR (NEW.original_payment_id IS NOT NULL AND ROUND(NEW.approved_amount * 100) + coalesce((
      SELECT sum(ROUND(refunds.approved_amount * 100))
      FROM refunds
      WHERE refunds.original_payment_id = NEW.original_payment_id AND refunds.id != OLD.id
  ), 0) > coalesce((
      SELECT ROUND(payments.amount * 100)
      FROM payments WHERE payments.id = NEW.original_payment_id
  ), 0))
  OR (NEW.cancellation_id IS NOT NULL AND ROUND(NEW.approved_amount * 100) + coalesce((
      SELECT sum(ROUND(refunds.approved_amount * 100))
      FROM refunds
      WHERE refunds.cancellation_id = NEW.cancellation_id AND refunds.id != OLD.id
  ), 0) > coalesce((
      SELECT ROUND(cancellations.refund_due * 100)
      FROM cancellations WHERE cancellations.id = NEW.cancellation_id
  ), 0))
  OR (NEW.returned_delivery_item_id IS NOT NULL AND (
      NEW.returned_quantity IS NULL OR NEW.returned_quantity < 1
      OR NEW.returned_item_condition IS NULL
      OR NEW.returned_quantity + coalesce((
          SELECT sum(refunds.returned_quantity)
          FROM refunds
          WHERE refunds.returned_delivery_item_id = NEW.returned_delivery_item_id
            AND refunds.id != OLD.id
      ), 0) > coalesce((
          SELECT delivery_items.quantity_delivered
          FROM delivery_items WHERE delivery_items.id = NEW.returned_delivery_item_id
      ), 0)
  ))
BEGIN
    SELECT RAISE(ABORT, 'invalid refund correction');
END;

CREATE TRIGGER validate_cancellation_update
BEFORE UPDATE OF cancellation_discount, refund_due ON cancellations
WHEN NEW.cancellation_discount < 0
  OR NEW.cancellation_discount > coalesce((
      SELECT orders.approved_total FROM orders WHERE orders.id = NEW.order_id
  ), -1)
  OR NEW.refund_due < coalesce((
      SELECT sum(refunds.approved_amount) FROM refunds WHERE refunds.cancellation_id = OLD.id
  ), 0)
BEGIN
    SELECT RAISE(ABORT, 'invalid cancellation correction');
END;
