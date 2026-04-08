[![CodeFactor](https://www.codefactor.io/repository/github/rogertm/wasp/badge)](https://www.codefactor.io/repository/github/rogertm/wasp)

# WASP 🐝 &bull; Wow! Another starter plugin

## Introducción

**WASP** es un _starter_ plugin que facilita el desarrollo con WordPress. Con él podrás crear tus propios plugins de manera rápida, fácil y sencilla. **WASP** Puede ser usado como framework para crear Custom Post Types, Taxonomías, Meta Boxes, Páginas de administración, Terms Meta, Users Meta, etc.

**WASP** provee un conjunto de clases a las que solo le debes pasar un grupo de parámetros para crear los elementos que componen tu plugin.

**Tan fácil como:**

```php
<?php
namespace WASP\Post_Type;

use WASP\Posts\Post_Type;

class Post_Type_Book extends Post_Type
{
    public function __construct()
    {
        parent::__construct();

        // CPT slug
        $this->post_type = 'wasp-book';

        // CPT labels
        $this->labels = array(
            'name' => _x( 'Book', 'Post type general name', 'wasp' )
        );

        // CPT arguments
        $this->args = array(
            'public' => true
        );
    }
}

new WASP\Post_Type\Post_Type_Book;
```

## Instalación

### Manual

**WASP** se instala como cualquier otro plugin de WordPress, para ello debes descargar la [última versión](https://github.com/rogertm/wasp/archive/refs/heads/main.zip), descompactar el archivo `.zip` y copiar su contenido en el directorio `wp-content/plugins/` de tu instalación de WordPress. O subirlo usando el instalador de plugins de WordPress.

### Instalar vía Git

Puedes clonar este repositorio directamente desde GitHub.

```bash
$ cd /path/to/your/wordpress-site/wp-content/plugins/
$ git clone git@github.com:rogertm/wasp.git
```

## Modo de uso

Puedes usar este plugin de dos maneras:

### Plantilla

Puedes generar tu propio repositorio a partir de este y usarlo como un _template_, solo debes pulsar el botón **Use this template** que aparece en el encabezado de este repositorio.

Es recomendable; pero no obligatorio, cambiar algunas cosas para una mayor facilidad a la hora de trabajar:

1. **Namespace**: Buscar `WASP\` y reemplazar por `Your_Namespace\`.
2. **Prefijo de funciones**: Buscar `wasp_` y reemplazar por `your_function_prefix_`.
3. **Text domain**: Buscar `'wasp'` (entre comillas simples) y reemplazar por `'your-text-domain'`.
4. **Slug**: Buscar `wasp-` y reemplazar por `your-slug-`.
5. **Comentarios y documentación**: Buscar `WASP` y reemplazar por `Your project name`.
6. **Archivos**: Buscar todos los archivos dentro del directorio `/classes` y cambiar el `slug` de cada uno por el que se ha especificado en el paso **4**. Ej: `class-wasp-admin-page.php` por `class-your-slug-admin-page.php`. Hacer lo mismo con el archivo `wasp.php`en la raíz del plugin.
7. Editar la cabecera del plugin según sea necesario.

Es importante seguir estos pasos en el mismo orden que se muestran.

### Child Plugin

_Yes, a Child Plugin!_

Puedes desarrollar tu propio plugin y heredar a todas las funcionalidades que brinda **WASP** creando un _Child Plugin_.

```php
<?php
/**
 * Plugin Name: WASP Child 🐝
 * Description: Wow! Another starter "Child" plugin
 * Plugin URI: https://github.com/rogertm/wasp
 * Author: RogerTM
 * Author URI: https://rogertm.com
 * Version: 1.0.0
 * License: GPL2
 * Text Domain: wasp-child
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) )
    die;

if ( file_exists( WP_PLUGIN_DIR .'/wasp/wasp.php' ) )
	require WP_PLUGIN_DIR .'/wasp/wasp.php';
else
	wp_die( __( 'This plugin requires WASP', 'wasp-child' ), __( 'Bum! 💣', 'wasp-child' ) );

/** Your code goes here 😎 */
```

## Wasp Cli

Puedes usar WASP desde la línea de comandos para generar tus clases, incluso crear un nuevo plugin que herede todas las funcionalidades de **WASP**. Para ello debes instalar **Wasp Cli**  usando **Composer**.

```bash
$ cd /path/to/your/wordpress-site/wp-content/plugins/wasp
$ composer install
```

Una vez instaladas las dependencias puedes usar Wasp Cli desde la línea de comandos:

```bash
php cli/wasp create:post_type "Book"
```

### Lista de comandos

### `project:`

- `project:new`  Crea un nuevo **child plugin** que hereda de **WASP**
- `project:rename` Renombra cadenas y archivos en este plugin usando la configuración existente

### `create:`

- `create:admin_page` Crea un nuevo archivo de clase para **Página de Administración** usando la configuración del proyecto
- `create:admin_subpage` Crea un nuevo archivo de clase para **Subpágina de Administración** usando la configuración del proyecto
- `create:meta_box` Crea una nueva clase para **Meta Box** usando stubs y la configuración del proyecto
- `create:post_type` Crea una nueva clase para **Custom Post Type** usando stubs y la configuración del proyecto
- `create:setting_fields` Crea un nuevo archivo de clase para **Settings Fields** usando la configuración del proyecto
- `create:taxonomy` Crea una nueva clase para **Taxonomy** usando stubs y la configuración del proyecto
- `create:term_meta` Crea un nuevo archivo de clase para **Term Meta** usando la configuración del proyecto
- `create:user_meta` Crea un nuevo archivo de clase para **User Meta** usando la configuración del proyecto
- `create:shortcode` Crea un nuevo archivo de clase para **Shortcode** usando la configuración del proyecto
- `create:custom_columns` Crea un nuevo archivo de clase para **Custom Columns** usando la configuración del proyecto

## Documentación

Puedes ver todos los detalles referentes al uso de **WASP** en la Wiki de este mismo repositorio 👉 https://github.com/rogertm/wasp/wiki

## Licencia

**WASP** es un programa de código abierto y se distribuye bajo licencia [GNU General Public License v2.0](https://github.com/rogertm/wasp/blob/main/LICENSE).

_Happy coding!_
