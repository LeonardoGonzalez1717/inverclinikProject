<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$response = ['status' => 'error', 'message' => '', 'html' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    $action = $_POST['action'];
    $tipo = $_POST['tipo_importacion'];

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
                
                $htmlVistaPrevia = '<table class="table table-bordered table-sm">';
                $filasValidas = 0;

                if ($tipo === '1') {
                    // --- PREPARAR DATOS DE VALIDACIÓN ---
                    $res_prod = $conn->query("SELECT CONCAT(nombre, '_', tipo_genero) as producto_genero FROM productos");
                    $rs_prod = $res_prod->fetch_all(MYSQLI_ASSOC);
                    $productos = array_column($rs_prod, 'producto_genero');

                    $generos = ["caballero", "dama", "niño", "niña", "unisex"];

                    $htmlVistaPrevia .= '<table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categotría</th>
                                <th>Genero</th>
                                <th>Descripcion</th>
                                <th>Precio</th>
                                <th>Validación</th>
                            </tr>
                        </thead>
                        <tbody>';

                    $filasValidas = 0;
                    $erroresTotales = 0;

                    for ($i = 2; $i < count($data); $i++) {
                        $producto = trim($data[$i][0]);
                        $categoria = trim($data[$i][1]);
                        $genero = strtolower(trim($data[$i][2]));
                        $descripcion = trim($data[$i][3]);
                        $precio = trim($data[$i][4]);

                        if (empty($producto) && empty($categoria) && empty($genero) && empty($descripcion) && empty($precio)) continue;

                        $erroresFila = [];

                        // --- VALIDACIONES ---
                        if (empty($producto) || empty($categoria) || empty($genero) || empty($precio)) {
                            $erroresFila[] = "Campos vacíos.";
                        }

                        $str = $producto . "_" . $genero;
                        if (in_array($str, $productos)) {
                            $erroresFila[] = "Producto ya existente.";
                        }

                        if (!in_array($genero, $generos)) {
                            $erroresFila[] = "Genero no valido.";
                        }

                        $precio = str_replace(',', '.', $precio);
                        if (!is_numeric($precio)) {
                            $erroresFila[] = "Costo debe ser numérico.";
                        }

                        $claseFila = count($erroresFila) > 0 ? 'table-danger' : 'table-success';
                        $mensajeError = implode('<br>', $erroresFila);

                        $htmlVistaPrevia .= "<tr class='{$claseFila}'>
                            <td>{$producto}</td>
                            <td>{$categoria}</td>
                            <td>{$genero}</td>
                            <td>{$descripcion}</td>
                            <td>{$precio}</td>
                            <td>{$mensajeError}</td>
                        </tr>";

                        if (count($erroresFila) === 0) {
                            $filasValidas++;
                        } else {
                            $erroresTotales++;
                        }
                    }
                    
                    $htmlVistaPrevia .= '</tbody></table>';

                    if ($filasValidas > 0 || $erroresTotales > 0) {
                        echo '<div class="alert alert-info">Análisis finalizado: ' . $filasValidas . ' válidos, ' . $erroresTotales . ' con errores.</div>';
                        echo '<h4>Vista Previa</h4>';
                        echo $htmlVistaPrevia;
                        
                        if ($erroresTotales === 0) {
                            echo '<button type="button" class="btn btn-success" onclick="grabarDatos()">Confirmar e Importar</button>';
                        } else {
                            echo '<div class="alert alert-warning">Corrija los errores en rojo antes de importar.</div>';
                        }
                    }
                }  else if ($tipo === '2') {
                    // ESTRUCTURA INSUMOS
                    
                    $res_i = $conn->query("SELECT nombre FROM insumos");
                    $rs_i = $res_i->fetch_all(MYSQLI_ASSOC);
                    $insumos = array_column($rs_i, 'nombre');

                    $res_p = $conn->query("SELECT nombre FROM proveedores");
                    $rs_p = $res_p->fetch_all(MYSQLI_ASSOC);
                    $proveedores = array_column($rs_p, 'nombre');

                    $um = ["metro", "unidad", "kilo", "litro", "metro cuadrado", "carrete", "rollo", "pieza"];

                    $htmlVistaPrevia .= '<table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Insumo</th>
                                <th>Unidad de Medida</th>
                                <th>Costo</th>
                                <th>Proveedor</th>
                                <th>Validacion</th>
                            </tr>
                        </thead>
                        <tbody>';

                    $filasValidas = 0;
                    $erroresTotales = 0;

                    for ($i = 2; $i < count($data); $i++) {
                        $insumo = trim($data[$i][0]);
                        $unidad = strtolower(trim($data[$i][1]));
                        $costo = trim($data[$i][2]);
                        $prov = trim($data[$i][3]);
                        
                        if (empty($insumo) && empty($unidad) && empty($costo) && empty($prov)) continue;

                        $erroresFila = [];

                        // --- VALIDACIONES ---

                        if (empty($insumo) || empty($unidad) || empty($costo)) {
                            $erroresFila[] = "Campos vacíos.";
                        }

                        if (in_array($insumo, $insumos)) {
                            $erroresFila[] = "El insumo ya existe.";
                        }

                        if (!in_array($unidad, $um)) {
                            $erroresFila[] = "Unidad inválida.";
                        }

                        $costo = str_replace(',', '.', $costo);
                        if (!is_numeric($costo)) {
                            $erroresFila[] = "El costo debe ser numérico.";
                        }

                        if (!empty($prov)) {
                            if (!in_array($prov, $proveedores)) {
                                $erroresFila[] = "Proveedor no registrado.";
                            }
                        }
                        
                        $claseFila = count($erroresFila) > 0 ? 'table-danger' : 'table-success';
                        $mensajeError = implode('<br>', $erroresFila);

                        $htmlVistaPrevia .= "<tr class='{$claseFila}'>
                            <td>{$insumo}</td>
                            <td>{$unidad}</td>
                            <td>{$costo}</td>
                            <td>{$prov}</td>
                            <td>{$mensajeError}</td>
                        </tr>";

                        if (count($erroresFila) === 0) {
                            $filasValidas++;
                        } else {
                            $erroresTotales++;
                        }
                    }
                    
                    $htmlVistaPrevia .= '</tbody></table>';

                    if ($filasValidas > 0 || $erroresTotales > 0) {
                        echo '<div class="alert alert-info">Análisis finalizado: ' . $filasValidas . ' válidos, ' . $erroresTotales . ' con errores.</div>';
                        echo '<h4>Vista Previa</h4>';
                        echo $htmlVistaPrevia;
                        
                        if ($erroresTotales === 0) {
                            echo '<button type="button" class="btn btn-success" onclick="grabarDatos()">Confirmar e Importar</button>';
                        } else {
                            echo '<div class="alert alert-warning">Corrija los errores en rojo antes de importar.</div>';
                        }
                    }
                }
                
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
            break;
            
        case 'grabarDatos':
            if (empty($_FILES['archivo']['tmp_name'])) {
                echo '<div class="alert alert-danger">Error: No se encuentra el archivo para guardar.</div>';
                exit;
            }

            try {
                $spreadsheet = IOFactory::load($_FILES['archivo']['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray();
                
                $conn->begin_transaction();
                
                $registrosInsertados = 0;

                for ($i = 2; $i < count($data); $i++) {
                    if (empty($data[$i][0])) continue;

                    if ($tipo === '1') {
                        // INSERTAR PRODUCTOS
                        $nombre = $conn->real_escape_string(trim($data[$i][0]));
                        $categoria = $conn->real_escape_string(trim($data[$i][1]));
                        $genero = trim($data[$i][2]);
                        $descripcion = $conn->real_escape_string(trim($data[$i][3]));
                        $precio = str_replace(',', '.', $data[$i][4]);

                        $sql = "INSERT INTO productos (nombre, categoria, tipo_genero, descripcion, precio_unitario) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssd", $nombre, $categoria, $genero, $descripcion, $precio);
                    } else if ($tipo === '2') {
                        // INSERTAR INSUMOS
                        $nombre = $conn->real_escape_string(trim($data[$i][0]));
                        $unidad = trim($data[$i][1]);
                        $costo = str_replace(',', '.', $data[$i][2]);
                        $prov = $data[$i][3];
                        if (empty(trim($prov))) {
                            $prov = null;
                        }

                        $data[$i][0] = $conn->real_escape_string(trim($data[$i][0]));
                        $data[$i][2] = str_replace(',', '.', $data[$i][2]);
                        $sql = "INSERT INTO insumos (nombre, unidad_medida, costo_unitario, proveedor_id) VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssdi", $nombre, $unidad, $costo, $prov);
                    }
                    
                    if ($stmt->execute()) {
                        $registrosInsertados++;
                    }
                }
                
                $conn->commit(); 
                echo '<div class="alert alert-success">¡Éxito! Se guardaron ' . $registrosInsertados . ' registros.</div>';

            } catch (Exception $e) {
                $conn->rollback(); 
                echo '<div class="alert alert-danger">Error al guardar: ' . $e->getMessage() . '</div>';
            }
            break;
    }
}
?>