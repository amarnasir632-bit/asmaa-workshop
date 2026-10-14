CREATE UNIQUE INDEX cancellations_order_unique
    ON cancellations (order_id);

CREATE TRIGGER validate_cancellation_insert
BEFORE INSERT ON cancellations
WHEN NEW.cancellation_discount < 0
  OR NEW.cancellation_discount > coalesce((
      SELECT approved_total FROM orders WHERE orders.id = NEW.order_id
  ), -1)
  OR NOT EXISTS (
      SELECT 1 FROM orders
      WHERE orders.id = NEW.order_id AND orders.operational_status != 'cancelled'
  )
BEGIN
    SELECT RAISE(ABORT, 'invalid order cancellation');
END;

CREATE TRIGGER validate_expense_insert
BEFORE INSERT ON expenses
WHEN NEW.amount <= 0
  OR NOT EXISTS (
      SELECT 1 FROM expense_categories
      WHERE expense_categories.id = NEW.expense_category_id AND expense_categories.is_active = 1
  )
BEGIN
    SELECT RAISE(ABORT, 'invalid expense');
END;

CREATE TRIGGER validate_refund_insert
BEFORE INSERT ON refunds
WHEN NEW.approved_amount <= 0
  OR NEW.refunded_amount < 0
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
  OR (NEW.returned_delivery_item_id IS NOT NULL AND (
      NEW.returned_quantity IS NULL OR NEW.returned_quantity < 1
      OR NEW.returned_item_condition IS NULL
      OR NOT EXISTS (
          SELECT 1 FROM delivery_items
          JOIN deliveries ON deliveries.id = delivery_items.delivery_id
          WHERE delivery_items.id = NEW.returned_delivery_item_id AND deliveries.order_id = NEW.order_id
      )
      OR NEW.returned_quantity + coalesce((
          SELECT sum(refunds.returned_quantity)
          FROM refunds
          WHERE refunds.returned_delivery_item_id = NEW.returned_delivery_item_id
      ), 0) > coalesce((
          SELECT delivery_items.quantity_delivered
          FROM delivery_items WHERE delivery_items.id = NEW.returned_delivery_item_id
      ), 0)
  ))
  OR (NEW.returned_delivery_item_id IS NULL AND (
      NEW.returned_quantity IS NOT NULL OR NEW.returned_item_condition IS NOT NULL
  ))
  OR ROUND(NEW.approved_amount * 100) + coalesce((
      SELECT sum(ROUND(refunds.approved_amount * 100))
      FROM refunds WHERE refunds.order_id = NEW.order_id
  ), 0) > coalesce((
      SELECT sum(ROUND(payments.amount * 100))
      FROM payments
      WHERE payments.order_id = NEW.order_id
        AND (payments.method = 'cash' OR payments.verification_status = 'verified')
  ), 0)
  OR (NEW.original_payment_id IS NOT NULL AND ROUND(NEW.approved_amount * 100) + coalesce((
      SELECT sum(ROUND(refunds.approved_amount * 100))
      FROM refunds WHERE refunds.original_payment_id = NEW.original_payment_id
  ), 0) > coalesce((
      SELECT ROUND(payments.amount * 100)
      FROM payments WHERE payments.id = NEW.original_payment_id
  ), 0))
  OR (NEW.cancellation_id IS NOT NULL AND ROUND(NEW.approved_amount * 100) + coalesce((
      SELECT sum(ROUND(refunds.approved_amount * 100))
      FROM refunds WHERE refunds.cancellation_id = NEW.cancellation_id
  ), 0) > coalesce((
      SELECT ROUND(cancellations.refund_due * 100)
      FROM cancellations WHERE cancellations.id = NEW.cancellation_id
  ), 0))
BEGIN
    SELECT RAISE(ABORT, 'invalid or excessive refund approval');
END;

CREATE TRIGGER prevent_refund_transaction_overpayment
BEFORE INSERT ON refund_transactions
WHEN NEW.amount <= 0
  OR ROUND(NEW.amount * 100) + coalesce((
      SELECT sum(ROUND(refund_transactions.amount * 100))
      FROM refund_transactions
      WHERE refund_transactions.refund_id = NEW.refund_id
  ), 0) > coalesce((
      SELECT ROUND(refunds.approved_amount * 100)
      FROM refunds WHERE refunds.id = NEW.refund_id
  ), 0)
BEGIN
    SELECT RAISE(ABORT, 'refund transaction exceeds approval');
END;
