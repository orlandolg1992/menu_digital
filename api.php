<?php
// Establecer el tipo de contenido de la respuesta como JSON
header('Content-Type: application/json');

// Deshabilitar el almacenamiento en caché
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Definir la ruta de la base de datos SQLite
$databaseFile = __DIR__ . '/menu_manager.db';

// Función para conectar a la base de datos y crear tablas si no existen
function connectDb($dbFile) {
    try {
        $pdo = new PDO("sqlite:$dbFile");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Crear tabla 'users'
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Crear tabla 'businesses' (ahora con user_id y sin UNIQUE en 'name')
        $pdo->exec("CREATE TABLE IF NOT EXISTS businesses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL, -- Eliminada la restricción UNIQUE
            description TEXT,
            logo_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Crear tabla 'menu_categories'
        $pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            business_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            name_en TEXT,          -- Nuevo campo para inglés
            description_en TEXT,   -- Nuevo campo para inglés
            name_fr TEXT,          -- Nuevo campo para francés
            description_fr TEXT,   -- Nuevo campo para francés
            order_num INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
        )");
        // Las sentencias ALTER TABLE para categorías se mantienen para compatibilidad con DBs existentes
        // si la tabla ya existía sin estas columnas.
        try {
            $pdo->exec("ALTER TABLE menu_categories ADD COLUMN name_en TEXT");
            $pdo->exec("ALTER TABLE menu_categories ADD COLUMN description_en TEXT");
            $pdo->exec("ALTER TABLE menu_categories ADD COLUMN name_fr TEXT");
            $pdo->exec("ALTER TABLE menu_categories ADD COLUMN description_fr TEXT");
        } catch (PDOException $e) {
            // Ignorar si la columna ya existe (error "duplicate column name")
            if (strpos($e->getMessage(), 'duplicate column name') === false) {
                throw $e; // Relanzar otros errores
            }
        }


        // Crear tabla 'menu_items' (ahora con campos de idioma, precio opcional)
        $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            name_en TEXT,          -- Nuevo campo para inglés
            description_en TEXT,   -- Nuevo campo para inglés
            name_fr TEXT,          -- Nuevo campo para francés
            description_fr TEXT,   -- Nuevo campo para francés
            price REAL,            -- Precio ahora opcional (permite NULL)
            is_available BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE
        )");
        // Las sentencias ALTER TABLE para items se mantienen para compatibilidad con DBs existentes
        // si la tabla ya existía sin estas columnas.
        try {
            $pdo->exec("ALTER TABLE menu_items ADD COLUMN name_en TEXT");
            $pdo->exec("ALTER TABLE menu_items ADD COLUMN description_en TEXT");
            $pdo->exec("ALTER TABLE menu_items ADD COLUMN name_fr TEXT");
            $pdo->exec("ALTER TABLE menu_items ADD COLUMN description_fr TEXT");
            $pdo->exec("ALTER TABLE menu_items ADD COLUMN price REAL"); // Asegura que 'price' sea REAL y opcional
        } catch (PDOException $e) {
            // Ignorar si la columna ya existe o si hay un problema al añadirla
            if (strpos($e->getMessage(), 'duplicate column name') === false && strpos($e->getMessage(), 'duplicate column') === false) {
                throw $e; // Relanzar otros errores
            }
        }


        return $pdo;
    } catch (PDOException $e) {
        // Captura el error si la columna ya existe o si hay un problema al añadirla
        // No salimos si es un error de "duplicate column name"
        if (strpos($e->getMessage(), 'duplicate column name') === false) {
            echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos o creación de tabla: ' . $e->getMessage()]);
            exit();
        }
        return $pdo; // Continúa si el error es solo por columna duplicada
    }
}

// Conectar a la base de datos
$pdo = connectDb($databaseFile);

// Obtener la acción y el método de la solicitud
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Para solicitudes POST, decodificar el JSON del cuerpo
$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    // Log raw input for debugging
    error_log("Raw POST input for action $action: " . $rawInput);
    $input = json_decode($rawInput, true);
    // Log decoded input for debugging
    error_log("Decoded POST input for action $action: " . print_r($input, true));
}


// Función de utilidad para verificar la propiedad del negocio
function checkBusinessOwnership($pdo, $businessId, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE id = :business_id AND user_id = :user_id");
    $stmt->execute([':business_id' => $businessId, ':user_id' => $userId]);
    return $stmt->fetchColumn() > 0;
}

// Función de utilidad para verificar la propiedad de la categoría
function checkCategoryOwnership($pdo, $categoryId, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_categories mc JOIN businesses b ON mc.business_id = b.id WHERE mc.id = :category_id AND b.user_id = :user_id");
    $stmt->execute([':category_id' => $categoryId, ':user_id' => $userId]);
    return $stmt->fetchColumn() > 0;
}

// Función para verificar la propiedad de un item de menú
function checkMenuItemOwnership($pdo, $itemId, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items mi JOIN menu_categories mc ON mi.category_id = mc.id JOIN businesses b ON mc.business_id = b.id WHERE mi.id = :item_id AND b.user_id = :user_id");
    $stmt->execute([':item_id' => $itemId, ':user_id' => $userId]);
    return $stmt->fetchColumn() > 0;
}


try {
    switch ($action) {
        // --- Acciones de Autenticación ---
        case 'register':
            if ($method !== 'POST') throw new Exception('Método no permitido.');
            if (!isset($input['username']) || !isset($input['password'])) throw new Exception('Usuario y contraseña son requeridos.');

            $username = $input['username'];
            $password_hash = password_hash($input['password'], PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)");
            $stmt->execute([':username' => $username, ':password_hash' => $password_hash]);
            echo json_encode(['success' => true, 'message' => 'Registro exitoso.', 'user_id' => $pdo->lastInsertId(), 'username' => $username]);
            break;

        case 'login':
            if ($method !== 'POST') throw new Exception('Método no permitido.');
            if (!isset($input['username']) || !isset($input['password'])) throw new Exception('Usuario y contraseña son requeridos.');

            $username = $input['username'];
            $password = $input['password'];

            $stmt = $pdo->prepare("SELECT id, password_hash, username FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                echo json_encode(['success' => true, 'message' => 'Login exitoso.', 'user_id' => $user['id'], 'username' => $user['username']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
            }
            break;

        case 'googleLogin': // Simulación de login con Google
            if ($method !== 'POST') throw new Exception('Método no permitido.');
            
            $googleUsername = 'google_user'; // Nombre de usuario predefinido para Google
            $googlePasswordHash = password_hash('google_temp_password', PASSWORD_BCRYPT); // Contraseña temporal

            // Buscar si el usuario de Google ya existe
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = :username");
            $stmt->execute([':username' => $googleUsername]);
            $user = $stmt->fetch();

            if ($user) {
                // Si existe, simplemente loguear
                echo json_encode(['success' => true, 'message' => 'Login con Google exitoso.', 'user_id' => $user['id'], 'username' => $user['username']]);
            } else {
                // Si no existe, registrarlo
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)");
                $stmt->execute([':username' => $googleUsername, ':password_hash' => $googlePasswordHash]);
                echo json_encode(['success' => true, 'message' => 'Registro y Login con Google exitoso.', 'user_id' => $pdo->lastInsertId(), 'username' => $googleUsername]);
            }
            break;

        // --- Acciones para Negocios (requieren autenticación) ---
        case 'addBusiness':
            if ($method !== 'POST') throw new Exception('Método no permitido para esta acción.');
            if (!isset($input['user_id']) || !isset($input['name'])) throw new Exception('ID de usuario y nombre del negocio son requeridos.');

            $userId = $input['user_id'];
            $name = $input['name'];
            $description = $input['description'] ?? null;
            $logo_url = $input['logo_url'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO businesses (user_id, name, description, logo_url) VALUES (:user_id, :name, :description, :logo_url)");
            $stmt->execute([
                ':user_id' => $userId,
                ':name' => $name,
                ':description' => $description,
                ':logo_url' => $logo_url
            ]);
            echo json_encode(['success' => true, 'message' => 'Negocio añadido exitosamente.', 'id' => $pdo->lastInsertId()]);
            break;

        case 'getBusinesses':
            if ($method !== 'GET') throw new Exception('Método no permitido para esta acción.');
            if (!isset($_GET['user_id'])) throw new Exception('ID de usuario es requerido para obtener negocios.');

            $userId = $_GET['user_id'];
            $stmt = $pdo->prepare("SELECT id, name, description, logo_url FROM businesses WHERE user_id = :user_id ORDER BY name ASC");
            $stmt->execute([':user_id' => $userId]);
            $businesses = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $businesses]);
            break;

        case 'deleteBusiness':
            if ($method !== 'POST') throw new Exception('Método no permitido para esta acción.');
            if (!isset($input['user_id']) || !isset($input['id'])) {
                throw new Exception('ID de usuario y ID de negocio son requeridos para eliminar.');
            }

            $userId = $input['user_id'];
            $businessId = $input['id'];

            // Verificar la propiedad del negocio antes de eliminar
            $stmt = $pdo->prepare("SELECT id FROM businesses WHERE id = :business_id AND user_id = :user_id");
            $stmt->execute([':business_id' => $businessId, ':user_id' => $userId]);
            $businessToDeleteData = $stmt->fetch();

            if (!$businessToDeleteData) {
                throw new Exception('No tienes permiso para eliminar este negocio o no existe.');
            }
            
            // Eliminar el negocio de la base de datos (esto activará la eliminación en cascada para categorías y elementos)
            $stmt = $pdo->prepare("DELETE FROM businesses WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $businessId, ':user_id' => $userId]);

            echo json_encode(['success' => true, 'message' => 'Negocio y todos sus datos asociados eliminados exitosamente.']);
            break;

        // --- Acciones para Categorías de Menú (requieren autenticación y propiedad) ---
        case 'addCategory':
            if ($method !== 'POST') throw new Exception('Método no permitido para esta acción.');
            if (!isset($input['user_id']) || !isset($input['business_id']) || !isset($input['name'])) throw new Exception('ID de usuario, ID de negocio y nombre de categoría son requeridos.');

            $userId = $input['user_id'];
            $businessId = $input['business_id'];
            $name = $input['name'];
            $description = $input['description'] ?? null;
            $name_en = $input['name_en'] ?? null;
            $description_en = $input['description_en'] ?? null;
            $name_fr = $input['name_fr'] ?? null;
            $description_fr = $input['description_fr'] ?? null;
            $order_num = $input['order_num'] ?? 0;

            if (!checkBusinessOwnership($pdo, $businessId, $userId)) {
                throw new Exception('No tienes permiso para añadir categorías a este negocio.');
            }

            $stmt = $pdo->prepare("INSERT INTO menu_categories (business_id, name, description, name_en, description_en, name_fr, description_fr, order_num) VALUES (:business_id, :name, :description, :name_en, :description_en, :name_fr, :description_fr, :order_num)");
            $stmt->execute([
                ':business_id' => $businessId,
                ':name' => $name,
                ':description' => $description,
                ':name_en' => $name_en,
                ':description_en' => $description_en,
                ':name_fr' => $name_fr,
                ':description_fr' => $description_fr,
                ':order_num' => $order_num
            ]);
            echo json_encode(['success' => true, 'message' => 'Categoría añadida exitosamente.', 'id' => $pdo->lastInsertId()]);
            break;

        case 'getCategories':
            if ($method !== 'GET') throw new Exception('Método no permitido para esta acción.');
            if (!isset($_GET['user_id']) || !isset($_GET['business_id'])) throw new Exception('ID de usuario y ID de negocio son requeridos para obtener categorías.');

            $userId = $_GET['user_id'];
            $businessId = $_GET['business_id'];

            if (!checkBusinessOwnership($pdo, $businessId, $userId)) {
                throw new Exception('No tienes permiso para ver las categorías de este negocio.');
            }

            $stmt = $pdo->prepare("SELECT id, name, description, name_en, description_en, name_fr, description_fr, order_num FROM menu_categories WHERE business_id = :business_id ORDER BY order_num ASC, name ASC");
            $stmt->execute([':business_id' => $businessId]);
            $categories = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $categories]);
            break;

        case 'deleteCategory':
            if ($method !== 'POST') throw new Exception('Método no permitido para esta acción.');
            if (!isset($input['user_id']) || !isset($input['id'])) {
                error_log("DELETE_CATEGORY_ERROR: user_id or id missing from input. Input: " . print_r($input, true));
                throw new Exception('ID de usuario y ID de categoría son requeridos para eliminar.');
            }

            $userId = $input['user_id'];
            $categoryId = $input['id'];

            error_log("DELETE_CATEGORY_DEBUG: Attempting to delete category. User ID: $userId, Category ID: $categoryId");

            // Verificar la propiedad de la categoría antes de eliminar
            $isOwner = checkCategoryOwnership($pdo, $categoryId, $userId);
            error_log("DELETE_CATEGORY_DEBUG: Ownership check result for Category ID $categoryId by User ID $userId: " . ($isOwner ? 'TRUE' : 'FALSE'));

            if (!$isOwner) {
                throw new Exception('No tienes permiso para eliminar esta categoría o no existe.');
            }
            
            // Eliminar la categoría de la base de datos (esto activará la eliminación en cascada para los elementos)
            $stmt = $pdo->prepare("DELETE FROM menu_categories WHERE id = :id");
            $stmt->execute([':id' => $categoryId]);
            error_log("DELETE_CATEGORY_DEBUG: Category ID $categoryId deleted successfully.");

            echo json_encode(['success' => true, 'message' => 'Categoría y todos sus elementos asociados eliminados exitosamente.']);
            break;
            
        // --- Acciones para Elementos de Menú (requieren autenticación y propiedad) ---
        case 'addMenuItem':
            if ($method !== 'POST') throw new Exception('Método no permitido para esta acción.');
            // Los datos vienen del $input (JSON decodificado)
            if (!isset($input['user_id']) || !isset($input['category_id']) || !isset($input['name'])) {
                throw new Exception('ID de usuario, ID de categoría y nombre son requeridos.');
            }

            $userId = $input['user_id'];
            $categoryId = $input['category_id'];
            $name = $input['name'];
            $description = $input['description'] ?? null;
            $name_en = $input['name_en'] ?? null;
            $description_en = $input['description_en'] ?? null;
            $name_fr = $input['name_fr'] ?? null;
            $description_fr = $input['description_fr'] ?? null;
            $price = isset($input['price']) && $input['price'] !== '' ? (float)$input['price'] : null;
            $is_available = $input['is_available'] ?? 1;

            if (!checkCategoryOwnership($pdo, $categoryId, $userId)) {
                throw new Exception('No tienes permiso para añadir elementos a esta categoría.');
            }

            $stmt = $pdo->prepare("INSERT INTO menu_items (category_id, name, description, name_en, description_en, name_fr, description_fr, price, is_available) VALUES (:category_id, :name, :description, :name_en, :description_en, :name_fr, :description_fr, :price, :is_available)");
            $stmt->execute([
                ':category_id' => $categoryId,
                ':name' => $name,
                ':description' => $description,
                ':name_en' => $name_en,
                ':description_en' => $description_en,
                ':name_fr' => $name_fr,
                ':description_fr' => $description_fr,
                ':price' => $price,
                ':is_available' => $is_available
            ]);
            echo json_encode(['success' => true, 'message' => 'Elemento de menú añadido exitosamente.', 'id' => $pdo->lastInsertId()]);
            break;

        case 'updateMenuItem':
            if ($method !== 'POST') throw new Exception('Método no permitido para esta acción.');
            if (!isset($input['user_id']) || !isset($input['id'])) {
                throw new Exception('ID de usuario y ID de elemento son requeridos para actualizar.');
            }

            $userId = $input['user_id'];
            $itemId = $input['id'];

            if (!checkMenuItemOwnership($pdo, $itemId, $userId)) {
                throw new Exception('No tienes permiso para actualizar este elemento o no existe.');
            }

            // Construir la consulta de actualización dinámicamente
            $setClauses = [];
            $params = [':id' => $itemId];

            if (isset($input['name'])) {
                $setClauses[] = 'name = :name';
                $params[':name'] = $input['name'];
            }
            if (isset($input['description'])) {
                $setClauses[] = 'description = :description';
                $params[':description'] = $input['description'];
            }
            if (isset($input['name_en'])) {
                $setClauses[] = 'name_en = :name_en';
                $params[':name_en'] = $input['name_en'];
            }
            if (isset($input['description_en'])) {
                $setClauses[] = 'description_en = :description_en';
                $params[':description_en'] = $input['description_en'];
            }
            if (isset($input['name_fr'])) {
                $setClauses[] = 'name_fr = :name_fr';
                $params[':name_fr'] = $input['name_fr'];
            }
            if (isset($input['description_fr'])) {
                $setClauses[] = 'description_fr = :description_fr';
                $params[':description_fr'] = $input['description_fr'];
            }
            // Price can be explicitly set to NULL if empty string is passed
            if (array_key_exists('price', $input)) {
                $setClauses[] = 'price = :price';
                $params[':price'] = ($input['price'] === '' || $input['price'] === null) ? null : (float)$input['price'];
            }
            if (isset($input['is_available'])) {
                $setClauses[] = 'is_available = :is_available';
                $params[':is_available'] = (int)$input['is_available'];
            }

            if (empty($setClauses)) {
                throw new Exception('No hay datos para actualizar.');
            }

            $query = "UPDATE menu_items SET " . implode(', ', $setClauses) . " WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'Elemento de menú actualizado exitosamente.']);
            break;


        case 'deleteMenuItem':
            if ($method !== 'POST') throw new Exception('Método no permitido para esta acción.');
            if (!isset($input['user_id']) || !isset($input['id'])) {
                error_log("DELETE_ITEM_ERROR: user_id or id missing from input. Input: " . print_r($input, true));
                throw new Exception('ID de usuario y ID de elemento son requeridos para eliminar.');
            }

            $userId = $input['user_id'];
            $itemId = $input['id'];

            error_log("DELETE_ITEM_DEBUG: Attempting to delete item. User ID: $userId, Item ID: $itemId");

            // Verificar la propiedad del item de menú antes de eliminar
            $isOwner = checkMenuItemOwnership($pdo, $itemId, $userId);
            error_log("DELETE_ITEM_DEBUG: Ownership check result for Item ID $itemId by User ID $userId: " . ($isOwner ? 'TRUE' : 'FALSE'));

            if (!$isOwner) {
                throw new Exception('No tienes permiso para eliminar este elemento o no existe.');
            }

            // Eliminar el elemento de la base de datos
            $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = :id");
            $stmt->execute([':id' => $itemId]);
            error_log("DELETE_ITEM_DEBUG: Item ID $itemId deleted successfully.");

            echo json_encode(['success' => true, 'message' => 'Elemento de menú eliminado exitosamente.']);
            break;

        case 'getMenuItems': // Obtiene elementos por categoría
            if ($method !== 'GET') throw new Exception('Método no permitido para esta acción.');
            if (!isset($_GET['user_id']) || !isset($_GET['category_id'])) throw new Exception('ID de usuario y ID de categoría son requeridos para obtener elementos del menú.');

            $userId = $_GET['user_id'];
            $categoryId = $_GET['category_id'];

            if (!checkCategoryOwnership($pdo, $categoryId, $userId)) {
                throw new Exception('No tienes permiso para ver los elementos de esta categoría.');
            }

            $stmt = $pdo->prepare("SELECT id, name, description, name_en, description_en, name_fr, description_fr, price, is_available FROM menu_items WHERE category_id = :category_id ORDER BY name ASC");
            $stmt->execute([':category_id' => $categoryId]);
            $items = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'getMenuByBusiness': // Obtiene todo el menú de un negocio (categorías y sus elementos)
            if ($method !== 'GET') throw new Exception('Método no permitido para esta acción.');
            if (!isset($_GET['user_id']) || !isset($_GET['business_id'])) throw new Exception('ID de usuario y ID de negocio son requeridos para obtener el menú completo.');

            $userId = $_GET['user_id'];
            $businessId = $_GET['business_id'];
            $menu = [];

            if (!checkBusinessOwnership($pdo, $businessId, $userId)) {
                throw new Exception('No tienes permiso para ver el menú de este negocio.');
            }

            // Obtener las categorías del negocio
            $stmtCategories = $pdo->prepare("SELECT id, name, description, name_en, description_en, name_fr, description_fr FROM menu_categories WHERE business_id = :business_id ORDER BY order_num ASC, name ASC");
            $stmtCategories->execute([':business_id' => $businessId]);
            $categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

            // Para cada categoría, obtener sus elementos
            foreach ($categories as &$category) { // Usar referencia para modificar el array original
                $stmtItems = $pdo->prepare("SELECT id, name, description, name_en, description_en, name_fr, description_fr, price, is_available FROM menu_items WHERE category_id = :category_id ORDER BY name ASC");
                $stmtItems->execute([':category_id' => $category['id']]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                $category['items'] = $items;
                $menu[] = $category;
            }
            unset($category); // Romper la referencia del último elemento

            echo json_encode(['success' => true, 'data' => $menu]);
            break;

        // --- Acciones para Vista Pública (NO requieren autenticación) ---
        case 'getPublicBusinesses':
            if ($method !== 'GET') throw new Exception('Método no permitido para esta acción.');
            $stmt = $pdo->query("SELECT id, name, description, logo_url FROM businesses ORDER BY name ASC");
            $businesses = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $businesses]);
            break;

        case 'getPublicMenuByBusiness':
            if ($method !== 'GET') throw new Exception('Método no permitido para esta acción.');
            if (!isset($_GET['business_id'])) throw new Exception('ID de negocio es requerido para obtener el menú público.');

            $business_id = $_GET['business_id'];
            $menu = [];

            // Obtener las categorías del negocio
            $stmtCategories = $pdo->prepare("SELECT id, name, description, name_en, description_en, name_fr, description_fr FROM menu_categories WHERE business_id = :business_id ORDER BY order_num ASC, name ASC");
            $stmtCategories->execute([':business_id' => $business_id]);
            $categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

            // Para cada categoría, obtener sus elementos
            foreach ($categories as &$category) { // Usar referencia para modificar el array original
                // Solo elementos disponibles
                $stmtItems = $pdo->prepare("SELECT id, name, description, name_en, description_en, name_fr, description_fr, price, is_available FROM menu_items WHERE category_id = :category_id AND is_available = 1 ORDER BY name ASC");
                $stmtItems->execute([':category_id' => $category['id']]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                $category['items'] = $items;
                $menu[] = $category;
            }
            unset($category); // Romper la referencia del último elemento

            echo json_encode(['success' => true, 'data' => $menu]);
            break;
            
        default:
            throw new Exception('Acción no válida o no especificada.');
    }
} catch (PDOException $e) {
    // Errores específicos de la base de datos (ej. UNIQUE constraint)
    if ($e->getCode() == '23000') {
        error_log("PDOException (23000): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: Dato duplicado, ya existe un registro con la misma información única.']);
    } else {
        error_log("PDOException: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    // Otros errores de la aplicación
    error_log("General Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Cerrar la conexión (opcional)
$pdo = null;
?>
