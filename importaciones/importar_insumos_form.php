<?php require_once('../template/header.php'); ?>
<?php
// Asumo que tu header.php ya tiene session_start() y la conexión $conn
$iduser = $_SESSION['iduser'];

// ... tu consulta SQL para traer datos del usuario ...
?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Datos</title>
    <style>
        /* CSS para solucionar la alineación */
        .fila-importar {
            display: flex;
            align-items: flex-end; 
            gap: 15px;
            margin-bottom: 20px;
        }
        .col-flexible {
            flex: 1; 
        }
        .btn-editar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #ccc; 
        }
    </style>
</head>
<body>
  <div class="main-content">
    <div class="container-wrapper">
      <div class="container-inner">
        <h2 class="main-title">Importar Datos</h2>

        <form id="formImportar" enctype="multipart/form-data">                
            
            <div class="fila-importar">
                <div class="col-flexible">
                    <label for="tipo_importacion">Tipo de Importación</label>
                    <select name="tipo_importacion" id="tipo_importacion" class="form-control" onchange="actualizarPlantilla()">
                        <option value="">Seleccione</option>
                        <option value="1">Productos</option>
                        <option value="2">Insumos</option>
                    </select>
                </div>

                <div>
                    <button type="button" id="btnDescargar" class="btn-editar" disabled onclick="descargarPlantilla()">
                        Descargar Plantilla
                    </button>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="archivo">Seleccione el archivo</label>
                <input type="file" class="form-control" name="archivo" id="archivo" accept=".xlsx, .xls, .csv" required>
            </div>
            
            <div class="form-group">
                <button type="button" class="btn-editar" onclick="enviarAnalisis()">Analizar Archivo</button>
            </div>
        </form>

        <div id="resultadoAnalisis" class="mt-3"></div>
      </div>
    </div>
  </div>

  <script>
    var rutaPlantilla = "";

    function actualizarPlantilla() {
        var tipo = document.getElementById('tipo_importacion').value;
        var btnDescarga = document.getElementById('btnDescargar');
        
        if (tipo === "") {
            btnDescarga.disabled = true;
            rutaPlantilla = "";
            return;
        }

        btnDescarga.disabled = false;
        
        if (tipo === '1') {
            rutaPlantilla = '../importaciones/Plantilla%20Importacion%20Productos.xlsx';
        } else if (tipo === '2') {
            rutaPlantilla = '../importaciones/Plantilla%20Importacion%20Insumos.xlsx';
        }
    }

    function descargarPlantilla() {
        if (rutaPlantilla !== "") {
            window.location.href = rutaPlantilla; 
        }
    }

    function enviarAnalisis() {
        var formElement = document.getElementById('formImportar');
        var formData = new FormData(formElement);
        formData.append('action', 'analizarArchivo');

        var resultadoDiv = document.getElementById('resultadoAnalisis');
        resultadoDiv.innerHTML = '<div class="alert alert-info">Analizando...</div>';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'importar_insumos_datos.php', true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                resultadoDiv.innerHTML = xhr.responseText;
            } else {
                resultadoDiv.innerHTML = '<div class="alert alert-danger">Error en el servidor.</div>';
            }
        };
        
        xhr.send(formData);
    }

    function grabarDatos() {
    var formElement = document.getElementById('formImportar');
    var formData = new FormData(formElement);
    formData.append('action', 'grabarDatos');

    var resultadoDiv = document.getElementById('resultadoAnalisis');
    resultadoDiv.innerHTML = '<div class="alert alert-info">Guardando...</div>';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'importar_insumos_datos.php', true);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            resultadoDiv.innerHTML = xhr.responseText;
        }
    };
    
    xhr.send(formData);
}
  </script>
</body>
</html>