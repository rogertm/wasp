# Wasp Cli

Puedes usar WASP desde la línea de comandos para generar tus clases, incluso crear un nuevo plugin que herede todas las funcionalidades de **WASP**. Para ello debes instalar **Wasp Cli**  usando **Composer**.

```bash
$ cd /path/to/your/wordpress-site/wp-content/plugins/wasp
$ composer install
```

Una vez instaladas las dependencias puedes usar Wasp Cli desde la línea de comandos:

```bash
php cli/wasp create:post_type "Book"
```

## Lista de comandos

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

## `wasp.sh` + autocompletion

Este directorio incluye:

- `wasp.sh`: wrapper para ejecutar `wasp` o `php cli/wasp` desde cualquier carpeta dentro del proyecto.
- `wasp-completion.sh`: autocompletado para `wasp` y `php cli/wasp`.

### 1) Instalación global de `wasp`

#### Opción recomendada (solo tu usuario, sin `sudo`)

```bash
chmod +x /path/to/your/wordpress-site/wp-content/plugins/wasp/cli/wasp.sh
mkdir -p ~/.local/bin
cp /path/to/your/wordpress-site/wp-content/plugins/wasp/cli/wasp.sh ~/.local/bin/wasp
chmod +x ~/.local/bin/wasp
```

Asegúrate de tener `~/.local/bin` en tu `PATH`.

```bash
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc
# o ~/.zshrc si usas zsh
```

#### Opción sistema completo (todos los usuarios)

```bash
chmod +x /path/to/your/wordpress-site/wp-content/plugins/wasp/cli/wasp.sh
sudo cp /path/to/your/wordpress-site/wp-content/plugins/wasp/cli/wasp.sh /usr/local/bin/wasp
sudo chmod +x /usr/local/bin/wasp
```

### 2) Activar autocompletado

#### Bash

```bash
echo 'source /path/to/your/wordpress-site/wp-content/plugins/wasp/cli/wasp-completion.sh' >> ~/.bashrc
source ~/.bashrc
```

#### Zsh

```bash
echo 'source /path/to/your/wordpress-site/wp-content/plugins/wasp/cli/wasp-completion.sh' >> ~/.zshrc
source ~/.zshrc
```

### 3) Uso

Desde cualquier carpeta dentro del plugin/proyecto:

```bash
wasp list
wasp project:new "Mi Plugin"
wasp create:post_type "Libro"
```

También funciona el comando directo:

```bash
php cli/wasp list
```

### 4) Verificación rápida

```bash
wasp --help
```

Si no encuentra el script, revisa:

- que estés dentro de una ruta donde exista `cli/wasp` (actual o padres),
- o que la copia global en `/usr/local/bin/wasp` o `~/.local/bin/wasp` exista y tenga permisos de ejecución.

## Documentación

Puedes ver todos los detalles referentes al uso de **Wasp Cli** en la Wiki de este mismo repositorio 👉 https://github.com/rogertm/wasp/wiki/Wasp-Cli

