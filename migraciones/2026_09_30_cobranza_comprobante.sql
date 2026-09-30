-- =====================================================================
-- 2026-09-30  Cobranza: comprobante obligatorio (pedido de Rosana)
-- =====================================================================
-- Hasta ahora el pago se registraba primero y la foto del comprobante se subía
-- después, aparte; el comprobante no quedaba anotado en la base (solo el archivo
-- images/depositos/<id>.<ext>). Esta columna guarda el nombre del archivo: el
-- pago se registra junto con su comprobante y el panel sabe si lo tiene.
-- Correr en producción ANTES de subir el código. Idempotente.
-- =====================================================================
ALTER TABLE Cobranza ADD COLUMN IF NOT EXISTS Comprobante VARCHAR(100) NULL DEFAULT NULL;
