<?php

declare(strict_types=1);

/**
 * Paginación reutilizable para listados cargados vía AJAX (HTML + JSON).
 *
 * Uso típico en *_data.php:
 *   $total = (int) $conn->query($countSql)->fetch_assoc()['c'];
 *   $pg = Pagination::fromInput($total, $_POST);
 *   $sqlLista = $sqlBase . $pg->limitClause();
 *   ob_start();
 *   // echo filas <tr>...</tr>
 *   $rowsHtml = ob_get_clean();
 *   Pagination::sendJsonList($rowsHtml, $pg);
 *   $conn->close();
 *   exit;
 */
final class Pagination
{
    private int $page;
    private int $perPage;
    private int $total;

    private function __construct(int $total, int $page, int $perPage, int $maxPerPage)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, min($maxPerPage, $perPage));
        $tp = $this->computeTotalPages();
        $this->page = max(1, min(max(1, $page), $tp));
    }

    /**
     * @param array<string,mixed> $input Normalmente $_POST o $_GET con page / per_page opcionales
     */
    public static function fromInput(int $totalRows, array $input, int $defaultPerPage = 15, int $maxPerPage = 100): self
    {
        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $per = isset($input['per_page']) ? (int) $input['per_page'] : $defaultPerPage;

        return new self($totalRows, $page, $per, $maxPerPage);
    }

    private function computeTotalPages(): int
    {
        if ($this->total === 0) {
            return 1;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function totalPages(): int
    {
        return $this->computeTotalPages();
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Fragmento SQL seguro (solo enteros internos) para añadir al final del SELECT.
     */
    public function limitClause(): string
    {
        return ' LIMIT ' . $this->perPage . ' OFFSET ' . $this->offset();
    }

    /** Número de fila de la primera entrada en esta página (base 1), para la columna "#". */
    public function rowNumberStart(): int
    {
        return $this->offset() + 1;
    }

    /**
     * @return array<string,mixed>
     */
    public static function jsonListResponse(string $rowsHtml, self $p): array
    {
        return [
            'success' => true,
            'rows_html' => $rowsHtml,
            'pagination_html' => $p->renderNavHtml(),
            'page' => $p->page(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
            'total_pages' => $p->totalPages(),
        ];
    }

    public static function sendJsonList(string $rowsHtml, self $p): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::jsonListResponse($rowsHtml, $p), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Cuenta filas de un SELECT arbitrario (por ejemplo con GROUP BY), vía subconsulta.
     */
    public static function countFromSubquery(mysqli $conn, string $selectSqlInner): int
    {
        $inner = trim($selectSqlInner);
        $sql = 'SELECT COUNT(*) AS __c FROM (' . $inner . ') AS __pag_sub';
        $res = $conn->query($sql);
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_assoc();

        return (int) ($row['__c'] ?? 0);
    }

    /**
     * Marcado compatible con Bootstrap 3 (el proyecto usa assets/css/bootstrap.css v3.1.1).
     * BS3 espera: ul.pagination > li > a|span (no existen .page-item ni .page-link).
     */
    public function renderNavHtml(string $ulExtraClasses = 'pagination-sm'): string
    {
        $tp = $this->totalPages();
        $cur = $this->page;

        if ($this->total === 0) {
            return '<nav class="crud-pagination-nav" aria-label="Paginación"><p class="text-muted small mb-0 text-center">Sin registros</p></nav>';
        }

        $ulClass = trim('pagination ' . $ulExtraClasses);

        $parts = [];
        $parts[] = '<nav class="crud-pagination-nav mt-2" aria-label="Paginación"><div class="text-center"><ul class="' . htmlspecialchars($ulClass, ENT_QUOTES, 'UTF-8') . '">';

        if ($cur <= 1) {
            $parts[] = '<li class="disabled"><span>Anterior</span></li>';
        } else {
            $parts[] = '<li><a href="#" data-page="' . ($cur - 1) . '">Anterior</a></li>';
        }

        $window = 5;
        $start = max(1, $cur - (int) floor($window / 2));
        $end = min($tp, $start + $window - 1);
        $start = max(1, $end - $window + 1);

        if ($start > 1) {
            $parts[] = '<li><a href="#" data-page="1">1</a></li>';
            if ($start > 2) {
                $parts[] = '<li class="disabled"><span>&hellip;</span></li>';
            }
        }

        for ($p = $start; $p <= $end; $p++) {
            if ($p === $cur) {
                $parts[] = '<li class="active"><a href="#">' . $p . '</a></li>';
            } else {
                $parts[] = '<li><a href="#" data-page="' . $p . '">' . $p . '</a></li>';
            }
        }

        if ($end < $tp) {
            if ($end < $tp - 1) {
                $parts[] = '<li class="disabled"><span>&hellip;</span></li>';
            }
            $parts[] = '<li><a href="#" data-page="' . $tp . '">' . $tp . '</a></li>';
        }

        if ($cur >= $tp) {
            $parts[] = '<li class="disabled"><span>Siguiente</span></li>';
        } else {
            $parts[] = '<li><a href="#" data-page="' . ($cur + 1) . '">Siguiente</a></li>';
        }

        $parts[] = '</ul></div>';
        $lastShown = min($this->total, $this->offset() + $this->perPage);
        $parts[] = '<div class="small text-muted text-center mt-1">Mostrando ' . $this->rowNumberStart() . '–' . $lastShown . ' de ' . $this->total . '</div>';
        $parts[] = '</nav>';

        return implode('', $parts);
    }
}
