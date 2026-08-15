# Manual de Implementación de Ciberseguridad — INVERCLINIK

Este documento detalla los pilares, justificaciones y mecanismos técnicos de ciberseguridad implementados en la plataforma **INVERCLINIK**. Su objetivo es servir como referencia técnica y auditoría interna para comprender **por qué**, **para qué** y **cómo** se resguarda la integridad, confidencialidad y disponibilidad de la información en el sistema.

---

## 1. El Por Qué: Justificación de Ciberseguridad (¿Por qué lo implementamos?)

INVERCLINIK es un sistema de gestión empresarial y de clientes que procesa y almacena información altamente crítica, tal como:

*   **Datos Financieros y Transaccionales**: Control de presupuestos, ventas, cuentas por cobrar y por pagar, tasas cambiarias e inventario de productos.
*   **Información de Clientes** Nombres, números de documento, direcciones de correo, teléfonos y credenciales de acceso.
*   **Continuidad de Negocio**: La operatividad diaria depende de que la base de datos de producción (`db_inverclinik`) esté disponible, sea íntegra y no sufra manipulaciones no autorizadas.

Un fallo de seguridad (como la inyección de código SQL, fuga de contraseñas o el secuestro de sesiones) podría resultar en pérdidas financieras directas, robo de propiedad intelectual, problemas legales por exposición de datos personales y la interrupción total de las actividades comerciales. Por ende, la seguridad de la información no es un añadido opcional, sino una **necesidad operativa fundamental**.

---

## 2. El Para Qué: Objetivos de Seguridad (¿Para qué lo implementamos?)

Las medidas de seguridad del proyecto se diseñaron y ejecutaron para lograr los siguientes objetivos específicos:

1.  **Garantizar la Confidencialidad de las Credenciales**: Impedir el almacenamiento de contraseñas en texto plano, neutralizando el impacto en caso de una filtración de la base de datos.
2.  **Prevenir la Inyección SQL (SQLi)**: Evitar que atacantes alteren o extraigan información sensible de la base de datos inyectando sentencias maliciosas en los formularios del sistema.
3.  **Prevenir ataques Cross-Site Scripting (XSS)**: Asegurar que las entradas de texto de los usuarios se limpien y escapen antes de renderizarse en el navegador de otros usuarios, bloqueando la ejecución de scripts maliciosos.
4.  **Establecer Control de Acceso Basado en Roles (RBAC)**: Asegurar que los usuarios con rol de "Lector" (Solo Lectura) no puedan realizar operaciones de escritura, edición o eliminación en la base de datos.
5.  **Garantizar la Trazabilidad (No Repudio)**: Registrar detalladamente cada movimiento clave en el sistema para saber quién, cuándo y desde qué dirección IP realizó una acción.
6.  **Implementar subidas de archivos seguras**: Validar de forma estricta los documentos e imágenes subidos al servidor para evitar que se ejecute código malicioso (webshells).
7.  **Resiliencia ante Desastres (Disponibilidad)**: Facilitar la restauración rápida del sistema mediante un módulo seguro de respaldos de base de datos.

---

## 3. El Cómo: Arquitectura y Detalles Técnicos (¿Cómo lo implementamos?)

A continuación se exponen las contramedidas implementadas directamente en el código PHP del sistema.

### A. Prevención de Inyección SQL mediante Sentencias Preparadas (Prepared Statements)
En lugar de concatenar directamente variables en las consultas SQL (lo que permite SQLi), el sistema implementa la interfaz de sentencias preparadas de `mysqli`. Esto separa la lógica de la consulta de los datos proporcionados por el usuario.

*   **Ejemplo de implementación**: En [login_cliente_data.php](file:///c:/xampp/htdocs/Proyecto/login_cliente_data.php#L13-L17) y en los módulos de gestión de catálogos y clientes.
```php
$sql = "SELECT id, nombre, password, role_id FROM clientes WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
```
El motor de base de datos precompila la estructura del query, haciendo imposible que el valor de `$email` modifique la lógica original de la consulta SQL.

### B. Criptografía de Contraseñas y Generación Segura de Claves
Las contraseñas de los usuarios internos y clientes se almacenan utilizando algoritmos de hashing criptográfico modernos en lugar de texto plano o algoritmos obsoletos (como MD5 o SHA-1).

*   **Hashing Robusto**: Se implementa `password_hash($password, PASSWORD_DEFAULT)`, el cual utiliza el estándar **Bcrypt** con sal (salt)
*   **Verificación Segura**: Se realiza mediante la función de tiempo constante `password_verify($clave, $row['password'])` para evitar ataques de canal lateral (timing attacks), como se observa en [index_data.php](file:///c:/xampp/htdocs/Proyecto/index_data.php#L37).
*   **Generación de Claves Temporales Criptográficamente Seguras**: En el flujo de recuperación de contraseña ([forgot_password_data.php](file:///c:/xampp/htdocs/Proyecto/forgot_password_data.php#L57-L59)), se utiliza `random_bytes()` en lugar de `rand()` para generar tokens impredecibles:
```php
$temp_pass = bin2hex(random_bytes(5)); // Contraseña temporal altamente aleatoria y segura
$hash = password_hash($temp_pass, PASSWORD_DEFAULT);
```

### C. Prevención de Enumeración de Usuarios
Para evitar que un atacante descubra qué correos electrónicos están registrados en la plataforma (técnica para preparar ataques dirigidos), el script de recuperación de contraseñas responde exactamente el mismo mensaje genérico de éxito, independientemente de si el correo existe o no en la base de datos:
```php
// Por seguridad: mismo mensaje si no existe el correo
echo json_encode([
    'success' => true,
    'message' => 'Si ese correo está registrado, recibirás una contraseña temporal. Revisa tu bandeja y úsala para iniciar sesión.'
]);
```

### D. Control de Acceso y Restricción de Escritura Global
El sistema cuenta con una verificación centralizada en el archivo de conexión principal ([connection.php](file:///c:/xampp/htdocs/Proyecto/connection/connection.php#L30-L66)). Si el usuario logueado posee el rol de **Lector** (`role_id` = 2):
1.  La función `esLector()` retorna `true`.
2.  La función `restringirEscritura()` intercepta cualquier llamada de modificación.
3.  Si la llamada se realiza por AJAX, retorna una estructura JSON estructurada con código de denegación.
4.  Si la petición es síncrona, inyecta un script dinámico que muestra una alerta visual con `SweetAlert2` y fuerza el retroceso de página mediante `window.history.back()`.


### F. Seguridad Avanzada en la Subida de Archivos
Permitir que los usuarios carguen archivos al servidor es un vector de ataque común si no se valida de forma idónea. En módulos como la carga de comprobantes de pago ([mis_cotizaciones_data.php](file:///c:/xampp/htdocs/Proyecto/cliente/mis_cotizaciones_data.php#L482-L514)) e imágenes de producto ([gestionar_productos_data.php](file:///c:/xampp/htdocs/Proyecto/src/gestionar_productos_data.php#L175-L197)):

2.  **Límite de Tamaño**: Restricciones de tamaño del archivo (e.g., 5MB para imágenes, 8MB para archivos PDF).
3.  **Renombrado Aleatorio**: Los archivos son renombrados con un patrón único usando prefijos, marcas de tiempo y el generador `uniqid()` para evitar colisiones de archivos y ataques de Path Traversal (manipulación de nombres para sobreescribir archivos críticos del sistema).
```php
$nuevoNombre = 'cot_' . $id_cot . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
```

### G. Auditoría y Trazabilidad Centralizada

*   Identificación exacta del actor (detecta automáticamente si es un Administrador/Staff, un Cliente o el propio Sistema).
*   Módulo afectado (Ventas, Clientes, Insumos, Perfiles, etc.).
*   Acción o mensaje descriptivo del cambio realizado.
*   Dirección IP de origen del cliente (`$_SERVER['REMOTE_ADDR']`).
*   Fecha y hora del evento.

### H. Integridad y Disponibilidad: Respaldos de Base de Datos
El servicio centralizado [RespaldoBdService.php](file:///c:/xampp/htdocs/Proyecto/lib/RespaldoBdService.php) utiliza de manera controlada los ejecutables nativos `mysqldump` y `mysql` para exportar e importar esquemas de base de datos completos. Almacena de manera controlada los archivos `.sql` en el directorio de almacenamiento restringido `/storage/respaldos_bd/` y registra sus metadatos correspondientes para mantener el control y prevenir pérdida de datos por fallos mecánicos o humanos.
