# Gestor de Boxes de Estudio – Prototipo PHP + MySQL

Prototipo académico de una plataforma web para consultar la disponibilidad y gestionar las reservas de los boxes de estudio de una biblioteca. Permite el acceso con perfiles de estudiante y bibliotecario, la administración de reservas y boxes, el control de asistencia, la gestión de sanciones y la simulación de notificaciones por correo.

El proyecto está desarrollado en **PHP** y utiliza **MySQL/MariaDB** como sistema de base de datos. Está preparado para ejecutarse localmente mediante **XAMPP**.

> **Importante:** este proyecto es un prototipo académico. El acceso denominado SSO/OIDC es una simulación local: la aplicación valida una contraseña almacenada en la base de datos, pero no se conecta a un proveedor institucional real. Los correos también son simulados; no se envían mediante Outlook ni Exchange.

## 1. Requisitos previos

Antes de instalar el proyecto, se requiere:

- Windows.
- XAMPP con Apache, PHP y MySQL/MariaDB.
- PHP **8.0 o superior**. El código utiliza `str_starts_with()`, disponible desde PHP 8.
- Extensión **PDO** y controlador **PDO MySQL** habilitados en PHP.
- Navegador web actualizado.
- Archivos del proyecto descargados o clonados desde este repositorio.

La interfaz utiliza Bootstrap 5.3.3 desde jsDelivr, por lo que se necesita conexión a Internet para cargar correctamente sus estilos mientras se use esa referencia CDN.

## 2. Instalación de XAMPP

1. Descargue e instale XAMPP desde su sitio oficial: https://www.apachefriends.org/
2. Abra el **Panel de Control de XAMPP**.
3. Inicie los servicios **Apache** y **MySQL**.
4. Compruebe que ambos servicios aparezcan en ejecución.
5. Si Apache o MySQL utilizan puertos distintos a los predeterminados, revise la configuración de XAMPP y utilice el puerto correspondiente al acceder a los servicios.

## 3. Instalación del proyecto

1. Descargue o clone este repositorio.
2. Dentro del proyecto, localice la carpeta `gestor_boxes`.
3. Copie esa carpeta en el directorio `htdocs` de XAMPP. La ruta esperada es:

   ```text
   C:\xampp\htdocs\gestor_boxes
   ```

4. Verifique que dentro de esa carpeta se encuentren, entre otros, los siguientes elementos:

   ```text
   gestor_boxes/
   ├── api/
   │   └── acciones.php
   ├── config/
   │   ├── db.php
   │   └── motor_tiempo.php
   ├── database/
   │   └── gestor_boxes.sql
   ├── admin.php
   ├── correos.php
   ├── index.php
   ├── portal.php
   └── README.md
   ```

5. Abra el Panel de Control de XAMPP e inicie Apache y MySQL.

## 4. Configuración de la base de datos

El archivo SQL del proyecto se encuentra en:

```text
database/gestor_boxes.sql
```

Este script crea la base de datos `gestor_boxes`, sus tablas y los datos iniciales de prueba. Para importarlo:

1. Abra el navegador e ingrese a `http://localhost/phpmyadmin`.
2. Seleccione la opción **Importar**.
3. Pulse **Seleccionar archivo** y elija `gestor_boxes/database/gestor_boxes.sql` dentro de la carpeta del proyecto.
4. Ejecute la importación.
5. Compruebe que se haya creado la base de datos `gestor_boxes`.

El script crea las siguientes tablas:

- `usuario`: cuentas, roles y estado de los usuarios.
- `box`: información y estado de los nueve boxes.
- `reserva`: reservas y sus estados.
- `sancion`: sanciones asociadas a inasistencias.
- `historial_acciones`: registro de operaciones realizadas.
- `sistema_reloj`: fecha y hora utilizadas por el reloj simulado.
- `correo_simulado`: mensajes y estado de envío simulado.

También incorpora nueve boxes (BOX-01 a BOX-09), cuatro cuentas de prueba y un reloj inicial configurado en `2026-11-23 09:00:00`.

## 5. Configuración de conexión PHP–MySQL

La conexión se configura en:

```text
config/db.php
```

Los parámetros definidos actualmente en el repositorio son:

| Parámetro | Valor configurado |
|---|---|
| Servidor | `localhost` |
| Base de datos | `gestor_boxes` |
| Usuario | `root` |
| Contraseña | Vacía (`''`) |
| Conexión | PDO con el controlador MySQL |
| Codificación | `utf8mb4` |

En una instalación estándar de XAMPP, estos valores suelen coincidir con la configuración local. Si su instalación utiliza otro servidor, usuario, contraseña o nombre de base de datos, modifique las variables `$host`, `$dbname`, `$user` y `$pass` en `config/db.php`.

El archivo configura PDO para utilizar excepciones ante errores, resultados asociativos y consultas preparadas nativas.

## 6. Ejecución del sistema

Una vez instalada la carpeta, importada la base de datos y configurada la conexión:

1. Confirme que Apache y MySQL estén iniciados en XAMPP.
2. Abra un navegador web.
3. Ingrese a:

   ```text
   http://localhost/gestor_boxes/
   ```

4. En la pantalla de acceso, seleccione una cuenta institucional de prueba.
5. Ingrese la contraseña inicial:

   ```text
   Box2026!
   ```

6. Pulse **Iniciar Sesión**. Según el rol de la cuenta, el sistema redirigirá al Portal del Estudiante o al Panel de Administración.

### Cuentas de prueba incluidas

El archivo SQL incorpora las siguientes cuentas:

| Perfil | Nombre | Identificador institucional | Correo |
|---|---|---|---|
| Estudiante | María Jara Ibarra | `20.456.789-1` | `m.jara@alumnos.santotomas.cl` |
| Estudiante | Leandro Rojas Pérez | `21.123.456-7` | `l.rojas@alumnos.santotomas.cl` |
| Estudiante | Sebastián Silva Soto | `20.987.654-3` | `s.silva@alumnos.santotomas.cl` |
| Bibliotecario | Rosa Lorca Andrade | `15.333.222-K` | `r.lorca@santotomas.cl` |

**Contraseña inicial para todas las cuentas de prueba:** `Box2026!`

Estas credenciales corresponden exclusivamente a los datos de demostración incluidos en el script SQL. No deben utilizarse como credenciales institucionales reales.

## 7. Verificación de la instalación

Para comprobar que el sistema quedó instalado correctamente, verifique los siguientes puntos:

- [ ] Apache y MySQL están iniciados en el Panel de Control de XAMPP.
- [ ] La base de datos `gestor_boxes` aparece en phpMyAdmin.
- [ ] Se importaron las siete tablas del sistema.
- [ ] Se encuentran registrados los nueve boxes (BOX-01 a BOX-09).
- [ ] Se encuentran disponibles las cuatro cuentas de prueba.
- [ ] La dirección `http://localhost/gestor_boxes/` muestra la pantalla de acceso.
- [ ] El inicio de sesión funciona con la contraseña de prueba.
- [ ] Una cuenta con rol estudiante ingresa al Portal del Estudiante.
- [ ] La cuenta con rol bibliotecario ingresa al Panel de Administración.
- [ ] Las funciones principales de consulta, reserva y administración responden correctamente.

## Funcionalidades incluidas

- Inicio de sesión de prueba con selección de cuenta, contraseña y perfiles diferenciados.
- Consulta del estado de los nueve boxes.
- Creación, confirmación y cancelación de reservas.
- Restricción de duración de reservas a una o dos horas.
- Reloj simulado para probar reglas temporales.
- Generación de correos institucionales simulados.
- Simulación de fallas y reintentos de envío de correo.
- Administración del estado operativo de los boxes.
- Creación y modificación administrativa de reservas.
- Registro de inasistencias y aplicación de sanciones.
- Consulta de estadísticas e historial de acciones.

## Observaciones

- El inicio de sesión SSO/OIDC es únicamente una simulación implementada en el prototipo; no realiza autenticación contra servicios institucionales externos.
- La bandeja de correo representa notificaciones almacenadas en la base de datos. No realiza envíos reales a cuentas Outlook o Exchange.
- El reloj del sistema es simulado y su valor inicial se almacena en la tabla `sistema_reloj`.
- La contraseña compartida se proporciona para facilitar las pruebas académicas. Para un despliegue real se deben implementar credenciales individuales, controles de seguridad y autenticación institucional.
