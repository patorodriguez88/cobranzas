-- =====================================================================
-- 2026-10-02  Cobranza: pago con cheque (carga manual del operador)
-- =====================================================================
-- Un pago con TipoOperacion = 'cheque' guarda:
--   Banco      -> banco emisor del cheque (sale de la lista BancosCheques)
--   Operacion  -> número de cheque
--   Fecha      -> fecha en que se recibió el pago (misma regla de siempre: hoy a 30 días atrás)
--   ChequeFecha     -> fecha del cheque (puede ser diferido)
--   ChequeLocalidad -> localidad del cheque
-- La foto del cheque va como el comprobante del pago (images/depositos/<id>.<ext>).
-- Correr en producción ANTES de subir el código. Idempotente.
-- =====================================================================

ALTER TABLE Cobranza ADD COLUMN IF NOT EXISTS ChequeFecha DATE NULL DEFAULT NULL;
ALTER TABLE Cobranza ADD COLUMN IF NOT EXISTS ChequeLocalidad VARCHAR(80) NULL DEFAULT NULL;

-- Bancos emisores de cheques: el operador puede ir agregando desde el formulario.
CREATE TABLE IF NOT EXISTS BancosCheques (
  id INT AUTO_INCREMENT PRIMARY KEY,
  Nombre VARCHAR(80) NOT NULL,
  Activo TINYINT(1) NOT NULL DEFAULT 1,
  Creado TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  Usuario VARCHAR(40) NULL,
  UNIQUE KEY uk_bancoscheques_nombre (Nombre)
);

INSERT IGNORE INTO BancosCheques (Nombre) VALUES
('Banco de la Nación Argentina'), ('Banco de la Provincia de Córdoba'), ('Banco Macro'),
('Banco Galicia'), ('Banco Santander'), ('BBVA'), ('Banco Credicoop'), ('ICBC'),
('Banco Patagonia'), ('Banco Supervielle'), ('Banco Hipotecario'), ('Banco Comafi'),
('Banco Ciudad'), ('Banco de la Provincia de Buenos Aires'), ('Banco Industrial (BIND)'),
('Banco Santa Fe'), ('Banco Entre Ríos'), ('Banco del Sol');
