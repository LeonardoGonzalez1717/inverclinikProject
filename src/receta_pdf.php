<?php
/**
 * Genera un PDF de la receta: lista de insumos con cantidad, fecha de creación y espacio para firma.
 * Parámetro GET: id = ID de la receta
 */
require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<p>Receta no especificada.</p>';
    exit;
}

// Datos de la receta
$stmt = $conn->prepare("
    SELECT 
        r.id,
        r.producto_id,
        r.rango_tallas_id,
        r.tipo_produccion_id,
        r.creado_en,
        p.nombre AS producto_nombre,
        rt.nombre_rango AS rango_tallas_nombre,
        tp.nombre AS tipo_produccion_nombre
    FROM recetas r
    INNER JOIN productos p ON r.producto_id = p.id
    INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
    INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$receta = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$receta) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<p>Receta no encontrada.</p>';
    exit;
}

$pid = (int) $receta['producto_id'];
$rid = (int) $receta['rango_tallas_id'];
$tid = (int) $receta['tipo_produccion_id'];

// Insumos de la receta
$stmt2 = $conn->prepare("
    SELECT 
        i.nombre AS insumo_nombre,
        i.unidad_medida,
        rp.cantidad_por_unidad
    FROM recetas_productos rp
    INNER JOIN insumos i ON i.id = rp.insumo_id
    WHERE rp.producto_id = ? AND rp.rango_tallas_id = ? AND rp.tipo_produccion_id = ?
    ORDER BY i.nombre
");
$stmt2->bind_param("iii", $pid, $rid, $tid);
$stmt2->execute();
$res2 = $stmt2->get_result();
$insumos = [];
while ($row = $res2->fetch_assoc()) {
    $insumos[] = $row;
}
$stmt2->close();
$conn->close();

$fecha_creacion = $receta['creado_en'] ? date('d/m/Y H:i', strtotime($receta['creado_en'])) : '—';
$titulo = 'Receta: ' . $receta['producto_nombre'] . ' — ' . $receta['rango_tallas_nombre'] . ' — ' . $receta['tipo_produccion_nombre'];

// Crear PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema');
$pdf->SetTitle($titulo);
$pdf->SetSubject('Receta de producción');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('helvetica', '', 10);
$pdf->AddPage();

// Título
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, $titulo, 0, 1, 'L');
$pdf->Ln(2);

// Fecha de creación
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Fecha de creación: ' . $fecha_creacion, 0, 1, 'L');
$pdf->Ln(6);

// Tabla de insumos
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(100, 7, 'Insumo', 1, 0, 'L', true);
$pdf->Cell(35, 7, 'Cantidad', 1, 0, 'C', true);
$pdf->Cell(55, 7, 'Unidad de medida', 1, 1, 'C', true);

$pdf->SetFont('helvetica', '', 9);
foreach ($insumos as $item) {
    $nombre = $item['insumo_nombre'] ?? '-';
    $cantidad = isset($item['cantidad_por_unidad']) ? number_format((float) $item['cantidad_por_unidad'], 4, '.', ',') : '-';
    $unidad = $item['unidad_medida'] ?? '-';
    $pdf->Cell(100, 7, $nombre, 1, 0, 'L');
    $pdf->Cell(35, 7, $cantidad, 1, 0, 'C');
    $pdf->Cell(55, 7, $unidad, 1, 1, 'C');
}

if (empty($insumos)) {
    $pdf->Cell(190, 7, 'Sin insumos registrados', 1, 1, 'C');
}

$pdf->Ln(15);

// Espacio para firma
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, '_________________________', 0, 1, 'L');
$pdf->Cell(0, 6, 'Firma y sello', 0, 1, 'L');

$pdf->Output('receta_' . $id . '.pdf', 'I');
