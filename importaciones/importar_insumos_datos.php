<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $tipo = $_POST['tipo_importacion']; // 1: Productos, 2: Insumos

    switch ($action) {
        case 'analizarArchivo':
            if (empty($_FILES['archivo']['tmp_name'])) {
                echo '<div class="alert alert-danger">No se subió archivo.</div>';
                exit;
            }

            try {
                $spreadsheet = IOFactory::load($_FILES['archivo']['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray();
                
                $filasValidas = 0;
                $erroresTotales = 0;
                $htmlVistaPrevia = '<table class="table table-bordered table-sm"><thead><tr>';

                if ($tipo === '1') {
                    // --- VALIDACIÓN DE PRODUCTOS ---
                    $res_prod = $conn->query("SELECT nombre, tipo_genero FROM productos");
                    $productos_existentes = [];
                    while($row = $res_prod->fetch_assoc()) {
                        $productos_existentes[] = strtolower(trim($row['nombre'])) . "_" . strtolower(trim($row['tipo_genero']));
                    }

                    $generos_validos = ["caballero", "dama", "niño", "niña", "unisex"];
                    $htmlVistaPrevia .= '<th>Nombre</th><th>Categoría</th><th>Género</th><th>Descripción</th><th>Precio</th><th>Validación</th></tr></thead><tbody>';

                    for ($i = 2; $i < count($data); $i++) {
                        if (empty(trim($data[$i][0]))) continue;

                        $nombre = trim($data[$i][0]);
                        $cat = trim($data[$i][1]);
                        $gen = strtolower(trim($data[$i][2]));
                        $desc = trim($data[$i][3]);
                        $precio = str_replace(',', '.', trim($data[$i][4]));

                        $err = [];
                        if (empty($nombre) || empty($gen)) $err[] = "Nombre y Género son obligatorios.";
                        if (in_array(strtolower($nombre)."_".$gen, $productos_existentes)) $err[] = "Producto/Género ya existe.";
                        if (!in_array($gen, $generos_validos)) $err[] = "Género inválido.";
                        if (!is_numeric($precio)) $err[] = "Precio debe ser numérico.";

                        $clase = count($err) > 0 ? 'table-danger' : 'table-success';
                        if(count($err) === 0) $filasValidas++; else $erroresTotales++;

                        $htmlVistaPrevia .= "<tr class='$clase'><td>$nombre</td><td>$cat</td><td>$gen</td><td>$desc</td><td>$precio</td><td>".implode("<br>", $err)."</td></tr>";
                    }
                } else {
                   // --- LÓGICA DE INSUMOS (CON STOCK, ALMACÉN Y ADICIONAL) ---
                    $res_ins = $conn->query("SELECT nombre FROM insumos");
                    $insumos_existentes = array_column($res_ins->fetch_all(MYSQLI_ASSOC), 'nombre');
                    
                    $res_prov = $conn->query("SELECT id, nombre FROM proveedores");
                    $proveedores_db = [];
                    while($row = $res_prov->fetch_assoc()) { $proveedores_db[strtolower(trim($row['nombre']))] = $row['id']; }

                    $res_alm = $conn->query("SELECT id, nombre, codigo FROM almacenes WHERE activo = 1");
                    $almacenes_db = [];
                    $almacenes_cod = [];
                    while($row = $res_alm->fetch_assoc()) { 
                        $almacenes_db[strtolower(trim($row['nombre']))] = $row['id']; 
                        
                        $almacenes_cod[strtolower(trim($row['codigo']))] = $row['id']; 
                    }

                    $um_validas = ["metro", "unidad", "kilo", "litro", "metro cuadrado", "carrete", "rollo", "pieza"];
                    $htmlVistaPrevia .= '<th>Insumo</th><th>Costo</th><th>Proveedor</th><th>Stocks (Min/Max)</th><th>Almacén</th><th>Adicional</th><th>Validación</th></tr></thead><tbody>';

                    for ($i = 2; $i < count($data); $i++) {
                        if (empty(trim($data[$i][0]))) continue;

                        $nombre = trim($data[$i][0]);
                        $um = strtolower(trim($data[$i][1]));
                        $costo = str_replace(',', '.', trim($data[$i][2]));
                        $prov_nombre = strtolower(trim($data[$i][3]));
                        $s_min = trim($data[$i][4]);
                        $s_max = trim($data[$i][5]);
                        $alm_nombre = strtolower(trim($data[$i][6]));
                        $es_adicional = strtolower(trim($data[$i][7])); // "si" o "no"

                        $err = [];
                        if (in_array($nombre, $insumos_existentes)) $err[] = "Ya existe este Insumo.";
                        if (!in_array($um, $um_validas)) $err[] = "Unidad de Medida inválida.";
                        if (!is_numeric($costo)) $err[] = "Costo no numérico.";
                        if (!is_numeric($s_min) || !is_numeric($s_max)) $err[] = "Stocks deben ser números.";
                        if ($s_min > $s_max) $err[] = "Mínimo > Máximo.";
                        if (!empty($prov_nombre) && !isset($proveedores_db[$prov_nombre])) $err[] = "Proveedor no registrado.";
                        if (!empty($alm_nombre) && !isset($almacenes_db[$alm_nombre]) && !isset($almacenes_cod[$alm_nombre])) $err[] = "Almacén no existe.";

                        $clase = count($err) > 0 ? 'table-danger' : 'table-success';
                        if(count($err) === 0) $filasValidas++; else $erroresTotales++;

                        $htmlVistaPrevia .= "<tr class='$clase'>
                            <td>$nombre</td><td>$costo</td>
                            <td>$prov_nombre</td><td>Min: $s_min / Max: $s_max</td>
                            <td>$alm_nombre</td><td>$es_adicional</td>
                            <td>".implode("<br>", $err)."</td></tr>";
                    }
                }

                $htmlVistaPrevia .= '</tbody></table>';
                echo '<div class="alert alert-info">Resultados: ' . $filasValidas . ' aptos, ' . $erroresTotales . ' con errores.</div>';
                echo $htmlVistaPrevia;
                if ($erroresTotales === 0 && $filasValidas > 0) echo '<button class="btn btn-success" onclick="grabarDatos()">Confirmar e Importar</button>';

            } catch (Exception $e) { echo "Error: " . $e->getMessage(); }
            break;

        case 'grabarDatos':
            try {
                $spreadsheet = IOFactory::load($_FILES['archivo']['tmp_name']);
                $data = $spreadsheet->getActiveSheet()->toArray();
                $conn->begin_transaction();
                $count = 0;

                if ($tipo === '1') {
                    $stmt = $conn->prepare("INSERT INTO productos (nombre, categoria, tipo_genero, descripcion, precio_unitario, activo) VALUES (?, ?, ?, ?, ?, 1)");
                    for ($i = 2; $i < count($data); $i++) {
                        if (empty(trim($data[$i][0]))) continue;
                        $precio = str_replace(',', '.', $data[$i][4]);
                        $stmt->bind_param("ssssd", $data[$i][0], $data[$i][1], $data[$i][2], $data[$i][3], $precio);
                        $stmt->execute();
                        $count++;
                    }
                } else {
                    $prov_res = $conn->query("SELECT id, nombre FROM proveedores");
                    $prov_map = []; while($r = $prov_res->fetch_assoc()){ $prov_map[strtolower(trim($r['nombre']))] = $r['id']; }
                    
                    $alm_res = $conn->query("SELECT id, nombre FROM almacenes");
                    $alm_map = []; while($r = $alm_res->fetch_assoc()){ $alm_map[strtolower(trim($r['nombre']))] = $r['id']; }

                    $stmt = $conn->prepare("INSERT INTO insumos (nombre, unidad_medida, costo_unitario, proveedor_id, stock_minimo, stock_maximo, almacen_id, adicional) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    for ($i = 2; $i < count($data); $i++) {
                        if (empty(trim($data[$i][0]))) continue;
                        
                        $costo = str_replace(',', '.', $data[$i][2]);
                        $p_id = $prov_map[strtolower(trim($data[$i][3] ?? ''))] ?? null;
                        $s_min = $data[$i][4] ?? 0;
                        $s_max = $data[$i][5] ?? 0;
                        $a_id = $alm_map[strtolower(trim($data[$i][6] ?? ''))] ?? 1;
                        $adicional = (strtolower(trim($data[$i][7] ?? '')) === '1') ? 1 : 0;
                        
                        $stmt->bind_param("ssdididi", 
                            $data[$i][0], $data[$i][1], $costo, $p_id, 
                            $s_min, $s_max, $a_id, $adicional
                        );
                        $stmt->execute();
                        $count++;
                    }
                }
                $conn->commit();
                echo '<div class="alert alert-success">Importación exitosa: '.$count.' insumos registrados.</div>';
            } catch (Exception $e) {
                $conn->rollback();
                echo '<div class="alert alert-danger">Error crítico: '.$e->getMessage().'</div>';
            }
            break;
    }
}