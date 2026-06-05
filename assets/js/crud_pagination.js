/**
 * Paginación CRUD: enlaza clics en .crud-pagination-nav y delega a loadFn(page).
 *
 * @param {string} containerSelector Selector estable que envuelve el HTML de paginación recargado (ej: #clientes-pagination).
 * @param {(page: number) => void} loadFn
 */
function bindCrudPagination(containerSelector, loadFn) {
    var $c = $(containerSelector);
    // Bootstrap 3: .pagination > li > a[data-page] (no .page-link)
    $c.off('click.crudPag', '.crud-pagination-nav a[data-page]');
    $c.on('click.crudPag', '.crud-pagination-nav a[data-page]', function (e) {
        e.preventDefault();
        var $li = $(this).closest('li');
        if ($li.hasClass('disabled') || $li.hasClass('active')) {
            return;
        }
        var page = parseInt($(this).data('page'), 10);
        if (!page || page < 1) {
            return;
        }
        loadFn(page);
    });
}

/**
 * POST listar_html esperando JSON Pagination (rows_html, pagination_html, …).
 *
 * @param {string} url
 * @param {object} baseData Campos fijos (action, filtros…); se añade page.
 * @param {string} tbodySelector
 * @param {string} paginationSelector Contenedor estable para pagination_html y delegación bindCrudPagination.
 */
function crudPostListadoPaginado(url, baseData, tbodySelector, paginationSelector, page) {
    var data = $.extend({}, baseData, { page: page || 1 });
    $.ajax({
        url: url,
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function (resp) {
            if (!resp || resp.success === false) {
                var msg = resp && resp.message ? resp.message : 'Error al cargar el listado';
                if (window.Swal) {
                    Swal.fire({ icon: 'error', text: msg });
                }
                return;
            }
            $(tbodySelector).html(resp.rows_html || '');
            $(paginationSelector).html(resp.pagination_html || '');
        },
        error: function (xhr) {
            var msg = 'Error de conexión';
            try {
                var j = JSON.parse(xhr.responseText);
                if (j.message) {
                    msg = j.message;
                }
            } catch (err) {}
            $(tbodySelector).html('<tr><td colspan="99" class="text-center text-danger">' + msg + '</td></tr>');
            $(paginationSelector).empty();
            if (window.Swal) {
                Swal.fire({ icon: 'error', text: msg });
            }
        }
    });
}
