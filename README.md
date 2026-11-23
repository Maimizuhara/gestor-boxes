[10:54 p.m., 22/11/2026] +56 9 3320 2772: Maria puedes pedirle a la IA con la que hiciste el prototipo si puede completar estos pasos con la información real del repositorio. es para colocarlo en el README
[10:54 p.m., 22/11/2026] +56 9 3320 2772: Gestor de Boxes – Prototipo PHP + MySQL

Prototipo de una plataforma web para consultar la disponibilidad y gestionar las reservas de los boxes de estudio de una biblioteca.

El sistema está desarrollado utilizando PHP y MySQL, y se ejecuta en un entorno local mediante XAMPP.

1. Requisitos previos

Antes de instalar el proyecto, se deben contar con los siguientes elementos:

Sistema operativo Windows.

XAMPP instalado, incluyendo Apache, PHP y MySQL.

Navegador web.

Archivos del proyecto descargados desde este repositorio.

2. Instalación de XAMPP

Descargar e instalar XAMPP.

Abrir el Panel de Control de XAMPP.

Verificar que los servicios Apache y MySQL estén disponibles.

Mantener la configuración de los servicios necesaria para ejecutar el proyecto localmente.

3. Instalación del proyecto

Descargar o clonar el repositorio.

Localizar la carpeta gestor_boxes.

Copiarla dentro del directorio:

C:\xampp\htdocs\gestor_boxes

Abrir el Panel de Control de XAMPP.

Iniciar los servicios Apache y MySQL.

4. Configuración de la base de datos

Abrir el navegador e ingresar a:

http://localhost/phpmyadmin

Crear una base de datos con el nombre gestor_boxes.

Seleccionar la base de datos creada.

Acceder a la opción Importar.

Seleccionar el archivo database/gestor_boxes.sql.

Ejecutar la importación para crear las tablas y cargar los datos incluidos en el archivo.

5. Configuración de conexión

El sistema requiere una conexión entre PHP y MySQL para consultar y almacenar la información.

Se debe verificar que los datos de conexión configurados en el proyecto coincidan con los parámetros del entorno local de XAMPP, especialmente el servidor, nombre de la base de datos, usuario y contraseña.

Si se utilizan valores diferentes a los configurados inicialmente, será necesario actualizar el archivo de conexión correspondiente.

6. Ejecución del sistema

Una vez iniciados los servicios y configurada la base de datos:

Abrir un navegador web.

Ingresar a la siguiente dirección:

http://localhost/gestor_boxes

Acceder a la plataforma utilizando una de las cuentas de prueba disponibles.

Contraseña inicial de las cuentas de prueba: Box2026!

7. Verificación 

Para comprobar que la instalación se realizó correctamente, verificar que:

Apache y MySQL se encuentren iniciados en XAMPP.

La base de datos gestor_boxes aparezca en phpMyAdmin.

Las tablas del sistema se hayan importado correctamente.

La dirección local permita acceder a la plataforma.

El inicio de sesión y las funcionalidades principales respondan correctamente.
