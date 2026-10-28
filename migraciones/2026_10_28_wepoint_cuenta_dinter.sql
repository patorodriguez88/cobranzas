-- =====================================================================
-- 2026-10-28  Wepoint: las ventas de Dinter van a la cuenta de DINTER SA
-- =====================================================================
-- Hasta ahora Cobranzas mandaba Dinter y Misas con un token de la empresa "Patricio" de Wepoint.
-- Desde ahora cada distribuidora usa la cuenta de su empresa (conexion/config_wepoint.php):
--   Dinter -> DINTER SA (cobranza@dintersa.com.ar) | Misas -> sigue en "Patricio" | Rak -> El Rak SAS
--
-- Correr en producción JUSTO ANTES de subir el código (sin ventas generándose en el medio).
-- Idempotente: se puede correr más de una vez.
-- =====================================================================

-- 1) Token de las cuentas que inician sesión con usuario y contraseña.
CREATE TABLE IF NOT EXISTS WepointTokens (
  cuenta VARCHAR(30) NOT NULL PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL
);

-- 2) Cuenta de Wepoint con la que se creó cada orden (para consultarla o borrarla con esa cuenta).
--    Las ya enviadas: Rak -> 'rak'; el resto -> 'patricio' (cuenta anterior).
ALTER TABLE Ventas ADD COLUMN IF NOT EXISTS wepoint_cuenta VARCHAR(30) NULL DEFAULT NULL AFTER wepoint_id_orden_venta;

UPDATE Ventas V
JOIN Clientes C ON C.id = V.idCliente
SET V.wepoint_cuenta = IF(UPPER(C.Distribuidora) = 'RAK', 'rak', 'patricio')
WHERE V.wepoint_id_orden_venta IS NOT NULL
  AND V.wepoint_cuenta IS NULL;

-- 3) Productos de Dinter en DINTER SA (antes iban a los de "Patricio" por Productos.idProductoWepoint).
--    Figuritas -> 5104 y Álbum -> 5103. Álbum tapa dura y Kit Panini: completar los IDs de DINTER SA
--    en @tapa_dura y @kit (si quedan en NULL, esos dos no se mapean y Cobranzas avisa al enviarlos).
SET @tapa_dura = NULL;
SET @kit = NULL;

INSERT INTO ProductosWepointEmpresa (idProducto, Distribuidora, idProductoWepoint, Activo, Fecha)
SELECT P.id, 'DINTER', M.wepoint, 1, NOW()
FROM Productos P
JOIN (
  SELECT 1 AS idProducto, 'FIGURITAS%' AS nombre, 5104 AS wepoint
  UNION ALL SELECT 2, 'ALBUM MUNDIAL%', 5103
  UNION ALL SELECT 4, 'ALBUM TAPA DURA%', @tapa_dura
  UNION ALL SELECT 5, 'KIT DE ACTUALIZACION%', @kit
) M ON M.idProducto = P.id AND P.Nombre LIKE M.nombre
WHERE M.wepoint IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM ProductosWepointEmpresa X
    WHERE X.idProducto = P.id AND X.Distribuidora = 'DINTER' AND X.Activo = 1
  );

-- 4) Clientes de Dinter: el vínculo con "Patricio" se guarda en wepoint_id_cliente_patricio (por si
--    hay que volver atrás). Los kioscos quedan vinculados a su cliente de DINTER SA (mismo código);
--    el resto queda sin vínculo y Cobranzas lo busca/crea en DINTER SA con su N° de cliente la
--    próxima vez que tenga una venta.
ALTER TABLE Clientes ADD COLUMN IF NOT EXISTS wepoint_id_cliente_patricio INT NULL DEFAULT NULL AFTER wepoint_id_cliente;

UPDATE Clientes
SET wepoint_id_cliente_patricio = wepoint_id_cliente
WHERE UPPER(Distribuidora) = 'DINTER'
  AND wepoint_id_cliente IS NOT NULL
  AND wepoint_id_cliente_patricio IS NULL;

CREATE TEMPORARY TABLE IF NOT EXISTS tmp_kioscos_dinter_sa (Ncliente INT NOT NULL PRIMARY KEY, wepoint INT NOT NULL);
DELETE FROM tmp_kioscos_dinter_sa;
INSERT INTO tmp_kioscos_dinter_sa (Ncliente, wepoint) VALUES
  (1, 789),
  (4, 790),
  (5, 791),
  (6, 792),
  (7, 793),
  (8, 794),
  (11, 795),
  (12, 796),
  (13, 797),
  (16, 798),
  (19, 799),
  (25, 800),
  (27, 801),
  (28, 802),
  (32, 803),
  (36, 804),
  (37, 805),
  (39, 806),
  (42, 807),
  (44, 808),
  (45, 809),
  (50, 810),
  (52, 811),
  (53, 812),
  (56, 813),
  (60, 814),
  (64, 815),
  (65, 816),
  (66, 817),
  (67, 818),
  (71, 819),
  (72, 820),
  (75, 821),
  (76, 822),
  (82, 823),
  (87, 824),
  (88, 825),
  (95, 826),
  (96, 827),
  (101, 828),
  (106, 829),
  (107, 830),
  (108, 831),
  (112, 832),
  (115, 833),
  (117, 834),
  (127, 835),
  (130, 836),
  (132, 837),
  (135, 838),
  (142, 839),
  (147, 840),
  (148, 841),
  (149, 842),
  (152, 843),
  (153, 844),
  (155, 845),
  (156, 846),
  (164, 847),
  (168, 848),
  (174, 849),
  (175, 850),
  (179, 851),
  (181, 852),
  (188, 853),
  (192, 854),
  (193, 855),
  (195, 856),
  (209, 857),
  (218, 858),
  (220, 859),
  (226, 860),
  (231, 861),
  (239, 862),
  (241, 863),
  (248, 864),
  (251, 865),
  (254, 866),
  (260, 867),
  (267, 868),
  (271, 869),
  (289, 870),
  (291, 871),
  (292, 872),
  (293, 873),
  (298, 874),
  (309, 875),
  (313, 876),
  (314, 877),
  (342, 878),
  (359, 879),
  (364, 880),
  (392, 881),
  (411, 882),
  (412, 883),
  (413, 884),
  (425, 885),
  (427, 886),
  (431, 887),
  (438, 888),
  (443, 889),
  (447, 890),
  (455, 891),
  (460, 892),
  (471, 893),
  (472, 894),
  (475, 895),
  (476, 896),
  (487, 897),
  (494, 898),
  (495, 899),
  (510, 900),
  (519, 901),
  (524, 902),
  (540, 903),
  (553, 904),
  (573, 905),
  (584, 906),
  (595, 907),
  (601, 908),
  (610, 909),
  (612, 910),
  (613, 911),
  (614, 912),
  (617, 913),
  (625, 914),
  (628, 915),
  (632, 916),
  (639, 917),
  (663, 918),
  (672, 919),
  (679, 920),
  (683, 921),
  (685, 922),
  (697, 923),
  (699, 924),
  (700, 925),
  (712, 926),
  (716, 927),
  (723, 928),
  (725, 929),
  (726, 930),
  (745, 931),
  (762, 932),
  (770, 933),
  (774, 934),
  (805, 935),
  (806, 936),
  (826, 937),
  (833, 938),
  (835, 939),
  (843, 940),
  (853, 941),
  (1059, 942),
  (1072, 943),
  (1077, 944),
  (1079, 945),
  (1085, 946),
  (1090, 947),
  (1097, 948),
  (1111, 949),
  (1602, 950),
  (1616, 951),
  (1617, 952),
  (1620, 953),
  (1622, 954),
  (1624, 955),
  (1629, 956),
  (1633, 957),
  (1635, 958),
  (1637, 959),
  (1645, 960),
  (1648, 961),
  (1650, 962),
  (1653, 963),
  (1677, 964),
  (1706, 965),
  (1708, 966),
  (4005, 967),
  (4006, 968),
  (4007, 969),
  (4018, 970),
  (4027, 971),
  (4030, 972),
  (4046, 973),
  (4052, 974),
  (4057, 975),
  (4061, 976),
  (4063, 977),
  (4064, 978),
  (4066, 979),
  (4068, 980),
  (4069, 981),
  (4071, 982),
  (4078, 983),
  (4081, 984),
  (4084, 985),
  (4087, 986),
  (4089, 987),
  (4096, 988),
  (4105, 989),
  (4106, 990),
  (4139, 991),
  (4144, 992),
  (4150, 993),
  (4512, 994),
  (4533, 995),
  (4535, 996),
  (4536, 997),
  (4985, 998);

UPDATE Clientes C
LEFT JOIN tmp_kioscos_dinter_sa K ON K.Ncliente = C.Ncliente
SET C.wepoint_id_cliente = K.wepoint
WHERE UPPER(C.Distribuidora) = 'DINTER';

DROP TEMPORARY TABLE IF EXISTS tmp_kioscos_dinter_sa;

-- Control: kioscos vinculados a DINTER SA (esperado: los kioscos que están en Cobranzas) y productos.
SELECT COUNT(*) AS clientes_dinter_vinculados_a_dinter_sa FROM Clientes WHERE UPPER(Distribuidora) = 'DINTER' AND wepoint_id_cliente IS NOT NULL;
SELECT P.id, P.Nombre, PWE.idProductoWepoint FROM ProductosWepointEmpresa PWE JOIN Productos P ON P.id = PWE.idProducto WHERE PWE.Distribuidora = 'DINTER' AND PWE.Activo = 1;
