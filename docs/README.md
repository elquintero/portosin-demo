# Cafetería Portosín 2.0

Versión preparada para alojar la web y administrar los contenidos desde el propio hosting, usando PHP + SQLite. GitHub queda únicamente como origen de la migración y como posible repositorio de preproducción.

## Requisitos del hosting

- PHP 8.1 o superior.
- Extensiones PHP `PDO` y `pdo_sqlite` habilitadas.
- Sesiones PHP habilitadas.
- Permisos de escritura para PHP en `private/storage/` e `imagenes/`.
- Recomendado: `post_max_size` de 32 MB o superior y `memory_limit` de 128 MB o superior.
- Hosting servido por HTTPS.

> Esta entrega requiere un hosting con PHP. No funcionará en alojamiento estático puro.

## Instalación

1. Haz una copia de seguridad de la web actual y de su carpeta de datos.
2. Descomprime el ZIP en el directorio raíz de la web, conservando los nombres de las carpetas.
3. Antes de ponerlo en producción, abre `private/server-config.php` y sustituye `admin_password_hash` por el hash de una contraseña propia. Puedes generar el hash con:

   ```bash
   php -r "echo password_hash('TU_CONTRASEÑA_LARGA', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Pega el resultado entre las comillas simples de `admin_password_hash`. La contraseña inicial de esta entrega es `Portosin-Cambiar-2026!`; cámbiala antes de poner la web en producción.
4. Comprueba que PHP puede escribir en `private/storage/` e `imagenes/`. Si el hosting no conserva permisos al descomprimir, asigna permisos de escritura al usuario PHP (normalmente carpetas 755/775 según el hosting; evita 777 salvo indicación del proveedor).
5. Comprueba que `https://tu-dominio/api/index.php` responde con JSON y no con un error PHP. Antes de migrar mostrará `migrated:false`.
6. Abre la web y activa el acceso al administrador tocando cinco veces seguidas el logotipo del pie de página. Entra en **Sistema** con la contraseña configurada.
7. Pulsa **Migrar datos desde GitHub**. La migración lee `data.json` desde el repositorio y rama indicados en `config.json`. El repositorio debe ser público para que el navegador pueda leer `raw.githubusercontent.com` sin credenciales.
8. Comprueba portada, menús, galería y contacto. Las imágenes se guardan en `imagenes/` y los datos en `private/storage/portosin.sqlite`.
9. Haz una edición de prueba, comprueba el borrador y pulsa **Publicar cambios en la web** cuando estés conforme.

La migración no elimina ni modifica el JSON de GitHub. Si ya existía una base SQLite, el contenido publicado anterior se guarda en el historial antes de sustituirse.

## Administrador

- **Visibilidad:** activar/desactivar portada, bloques, menú, precios, galería, contacto y redes. Ocultar no elimina los datos.
- **Edición:** los cambios se guardan como borrador en SQLite; la sesión del administrador ve su borrador para revisarlo.
- **Publicación:** la web pública solo recibe los cambios al pulsar “Publicar cambios en la web”.
- **Historial:** se conservan hasta 30 versiones publicadas anteriores. Se puede recuperar una como borrador y publicarla después.
- **Menús:** calendario, duplicación del último menú, creación en blanco, borrado y generación de imagen para WhatsApp.
- **Imágenes:** se optimizan en el navegador y el servidor las extrae a ficheros independientes; el JSON guarda rutas relativas.

## Archivos y almacenamiento

- `api/index.php`: API de lectura, borradores, publicación, autenticación, migración e historial.
- `private/server-config.php`: configuración privada y hash de contraseña.
- `private/storage/portosin.sqlite`: base SQLite creada automáticamente al primer acceso a la API.
- `imagenes/`: imágenes optimizadas. No se deben renombrar manualmente porque SQLite guarda sus rutas.
- `config.json`: repositorio y rama de GitHub usados para la migración.
- `data.json`: ya no es la fuente de producción. El `.htaccess` impide su descarga directa en Apache.

## Seguridad y servidor

- Cambia la contraseña inicial antes de publicar.
- Mantén HTTPS activado.
- No publiques `private/server-config.php`, `private/storage/` ni copias de seguridad. El ZIP incluye reglas `.htaccess` para Apache.
- Si el hosting usa Nginx, las reglas `.htaccess` no se aplican: configura denegación HTTP para `/private/` y `data.json` en el servidor o coloca `private/storage/` fuera del document root.
- Usa una contraseña larga y exclusiva. El administrador se autentica mediante sesión PHP `HttpOnly` y `SameSite=Strict`; la contraseña no se guarda en `localStorage`.
- Haz copias de seguridad periódicas de `private/storage/portosin.sqlite` y `imagenes/` desde el panel del hosting.

## Limitaciones conocidas de esta entrega

- La migración desde GitHub se hace desde el navegador y, por tanto, requiere que el repositorio sea público y accesible desde el navegador.
- El historial conserva versiones publicadas de los datos, pero no elimina automáticamente imágenes antiguas que ya no estén referenciadas. Conviene revisar el tamaño de `imagenes/` periódicamente.
- El comportamiento offline depende de los contenidos previamente cacheados por el dispositivo. Los cambios nuevos necesitan conexión para descargarse.


## Estructura de carpetas de esta entrega

```text
/
├── index.html                 # Web pública y panel de administración
├── manifest.webmanifest       # Configuración PWA
├── sw.js                      # Service Worker
├── api/
│   └── index.php              # API PHP
├── assets/
│   └── img/                   # Logotipos e iconos
├── imagenes/                  # Fotografías públicas de la web
├── private/                   # Configuración y datos no públicos
│   ├── server-config.php
│   ├── storage/portosin.sqlite
│   └── backups/
├── config.json                # Repositorio/rama de origen para migrar
├── data.json                  # JSON antiguo (solo referencia/migración)
└── docs/README.md
```

El administrador continúa integrado en `index.html` para no alterar innecesariamente la interfaz ni el flujo de migración. La API se ha trasladado a `api/index.php`, y todas las llamadas internas y rutas PWA se han actualizado. La configuración y SQLite quedan dentro de `private/`, protegida contra acceso HTTP directo en Apache.
