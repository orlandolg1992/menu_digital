Gestor de Menú Digital Gratis
Este proyecto es una aplicación web sencilla para gestionar menús digitales de negocios, permitiendo a los usuarios crear sus propios menús, organizarlos por categorías y elementos, y luego compartirlos públicamente. La aplicación incluye un sistema de autenticación de usuarios y una API backend para la gestión de datos.

Características
Autenticación de Usuarios: Registro e inicio de sesión con usuario y contraseña (simulación de Google Login).

Gestión de Negocios/Menús: Los usuarios autenticados pueden añadir, ver y eliminar sus propios menús.

Gestión de Categorías: Dentro de cada menú, los usuarios pueden crear, ver y eliminar categorías de menú.

Gestión de Elementos de Menú: Dentro de cada categoría, los usuarios pueden añadir, ver y eliminar elementos de menú con nombre, descripción, precio (opcional) y estado de disponibilidad.

Soporte Multilingüe: Campos para nombre y descripción en español, inglés y francés para categorías y elementos.

Vista Pública del Menú: Los menús pueden ser vistos públicamente a través de un enlace único, sin necesidad de autenticación.

Base de Datos SQLite: Almacenamiento de datos ligero y fácil de configurar.

Interfaz de Usuario Responsiva: Diseñada con Tailwind CSS para adaptarse a diferentes tamaños de pantalla.

Estructura del Proyecto
index.html: La interfaz de usuario del lado del cliente (frontend) construida con HTML, CSS (Tailwind CSS) y JavaScript.

api.php: El backend de la API que maneja las solicitudes HTTP, interactúa con la base de datos SQLite y gestiona la lógica de negocio.

menu_manager.db: El archivo de la base de datos SQLite que se creará automáticamente la primera vez que se ejecute api.php.

Requisitos
Para ejecutar este proyecto, necesitarás un entorno de servidor web que soporte PHP, como Apache o Nginx, y que tenga PHP instalado.

PHP: Versión 7.4 o superior.

Extensión PDO SQLite: Asegúrate de que la extensión php_sqlite3 o php_pdo_sqlite esté habilitada en tu configuración de php.ini.

Configuración y Ejecución
Sigue estos pasos para configurar y ejecutar el proyecto localmente:

Clonar o Descargar el Proyecto:
Guarda los archivos index.html y api.php en un directorio de tu servidor web (por ejemplo, htdocs para Apache, www para Nginx, o el directorio raíz de un servidor PHP incorporado).

Configurar el Servidor Web:
Asegúrate de que tu servidor web esté configurado para servir archivos PHP desde el directorio donde guardaste los archivos.

Acceder a la Aplicación:
Abre tu navegador web y navega a la URL donde se encuentra index.html. Por ejemplo, si lo colocaste en la raíz de tu servidor local, sería http://localhost/index.html o simplemente http://localhost/.

Base de Datos:
La primera vez que accedas a api.php (por ejemplo, al intentar registrar un usuario o cargar negocios), el archivo menu_manager.db se creará automáticamente en el mismo directorio que api.php. Si la base de datos ya existe, las tablas se verificarán y se crearán si no existen, o se añadirán las nuevas columnas si es necesario.

Uso de la Aplicación
1. Autenticación
Registrarse: Si no tienes una cuenta, haz clic en "¿No tienes cuenta? Regístrate" y completa el formulario.

Iniciar Sesión: Si ya tienes una cuenta, ingresa tu usuario y contraseña.

Iniciar Sesión con Google (Simulado): Haz clic en el botón "Iniciar Sesión con Google" para una autenticación simulada que crea un usuario genérico.

Ver Menús Públicos: Puedes ver los menús públicos sin iniciar sesión haciendo clic en "Ver Menús Públicos" en la pantalla de inicio de sesión.

2. Gestión de Menús (Negocios)
Una vez iniciado sesión, verás la sección "Menús".

Añadir Nuevo Menú: Haz clic en "Añadir Nuevo Menú" para abrir un modal y crear un nuevo menú (negocio).

Gestionar Menú: Haz clic en "Gestionar Menú" junto a un menú existente para entrar en su sección de gestión.

Ver Menú Público: Haz clic en "Ver Menú Público" para abrir una nueva pestaña con la URL pública de ese menú.

Eliminar Menú: Haz clic en "Eliminar Menú" para eliminar un menú y todos sus datos asociados (categorías y elementos). Se te pedirá confirmación.

3. Gestión de Categorías
Dentro de la sección de gestión de un menú:

Añadir Nueva Categoría: Haz clic en "Añadir Nueva Categoría" para añadir una nueva sección a tu menú. Puedes especificar nombres y descripciones en español, inglés y francés.

Eliminar Categoría: Haz clic en "Eliminar Categoría" junto a una categoría para eliminarla y todos sus elementos asociados. Se te pedirá confirmación.

4. Gestión de Elementos de Menú
Dentro de cada categoría:

Añadir Elemento a esta Categoría: Haz clic en este botón para añadir un nuevo plato o bebida a la categoría. Puedes especificar nombres, descripciones y precio (opcional) en español, inglés y francés.

Eliminar: Haz clic en "Eliminar" junto a un elemento para eliminarlo. Se te pedirá confirmación.

5. Vista Pública del Menú
Desde la página principal, puedes seleccionar un menú de la lista desplegable para ver su versión pública.

Cada menú tiene una URL pública única (ej. http://localhost/?view_menu=ID_DEL_NEGOCIO) que puedes compartir con tus clientes. Esta vista solo muestra los elementos marcados como disponibles.

La aplicación intentará mostrar los nombres y descripciones en el idioma preferido del navegador del usuario, con español, inglés y francés como opciones, y español como fallback.

Notas Adicionales
Seguridad: Este es un proyecto de demostración. Para un entorno de producción, se necesitarían medidas de seguridad adicionales, como HTTPS, validación de entrada más robusta, manejo de sesiones seguro y protección contra ataques CSRF/XSS.

Simulación de Google Login: La funcionalidad de "Iniciar Sesión con Google" es una simulación básica y no se conecta a la API real de Google.

Base de Datos SQLite: SQLite es ideal para aplicaciones pequeñas o de un solo usuario. Para aplicaciones con mayor concurrencia o escala, se recomendaría una base de datos más robusta como MySQL o PostgreSQL.
