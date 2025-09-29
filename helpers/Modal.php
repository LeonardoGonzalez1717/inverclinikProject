<?php
class Modal
{
    /**
     * Genera el HTML de un modal reutilizable
     *
     * @param array $config {
     *     @type string $id ID del modal (ej: 'miModal')
     *     @type string $titulo Título del modal
     *     @type string $mensaje Mensaje descriptivo (opcional)
     *     @type array $botones Lista de botones (solo si no usas 'contenido')
     *     @type string $contenido HTML personalizado para el body (ej: un formulario)
     *     @type bool $conFooter Incluir footer con botón "Cerrar" (por defecto: true)
     *     @type string $footer HTML personalizado para el footer (opcional)
     * }
     * @return string HTML del modal
     */
    public static function crear($config)
    {
        $id = $config['id'] ?? 'modal-' . uniqid();
        $titulo = $config['titulo'] ?? 'Modal';
        $mensaje = $config['mensaje'] ?? '';
        $botones = $config['botones'] ?? [];
        $contenido = $config['contenido'] ?? '';
        $conFooter = $config['conFooter'] ?? true;
        $footerPersonalizado = $config['footer'] ?? '';

        // Inicio del modal
        $html = "
        <div class=\"modal fade\" id=\"{$id}\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"{$id}Label\" aria-hidden=\"true\">
            <div class=\"modal-dialog\" role=\"document\">
                <div class=\"modal-content\">
                    <div class=\"modal-header\">
                        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Cerrar\">
                            <span aria-hidden=\"true\">&times;</span>
                        </button>
                        <h4 class=\"modal-title\" id=\"{$id}Label\">{$titulo}</h4>
                    </div>
                    <div class=\"modal-body\">";

        // Mensaje opcional
        if ($mensaje) {
            $html .= "<p>{$mensaje}</p>";
        }

        // Si se proporciona contenido personalizado, se usa
        if ($contenido) {
            $html .= $contenido;
        } 
        // Si no, se usan los botones (comportamiento anterior)
        elseif (!empty($botones)) {
            $html .= '<div class="list-group">';
            foreach ($botones as $btn) {
                $texto = htmlspecialchars($btn['texto'] ?? 'Botón', ENT_QUOTES, 'UTF-8');
                $icono = htmlspecialchars($btn['icono'] ?? 'fa-circle', ENT_QUOTES, 'UTF-8');
                $clase = htmlspecialchars($btn['clase'] ?? '', ENT_QUOTES, 'UTF-8');
                $onclick = isset($btn['onclick']) ? "onclick=\"{$btn['onclick']}\"" : '';
                $dataToggle = isset($btn['data_toggle']) ? "data-toggle=\"{$btn['data_toggle']}\"" : '';
                $dataTarget = isset($btn['data_target']) ? "data-target=\"{$btn['data_target']}\"" : '';

                $html .= "
                    <button type=\"button\" class=\"list-group-item {$clase}\" style=\"width:100%\" {$onclick} {$dataToggle} {$dataTarget}>
                        <i class=\"fa {$icono}\"></i> {$texto}
                    </button>";
            }
            $html .= '</div>';
        }

        $html .= "
                    </div>";

        // Footer
        if ($footerPersonalizado) {
            $html .= "<div class=\"modal-footer\">{$footerPersonalizado}</div>";
        } elseif ($conFooter) {
            $html .= "
                    <div class=\"modal-footer\">
                        <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Cerrar</button>
                    </div>";
        }

        $html .= "
                </div>
            </div>
        </div>";

        return $html;
    }
}