<?php
/**
 * Router - Định tuyến URL
 * 
 * Hỗ trợ:
 * - Route parameters: /users/{id}
 * - Middleware groups
 * - Method filtering (GET, POST, PUT, DELETE)
 * - Route prefix groups
 * 
 * @package Core
 */

namespace Core;

class Router
{
    /**
     * @var array Danh sách routes đã đăng ký
     */
    private $routes = [];
    
    /**
     * @var array Danh sách route groups
     */
    private $routeGroups = [];
    
    /**
     * @var string|null Current group ID khi đang trong group
     */
    private $currentGroup = null;
    
    /**
     * Thêm route GET
     */
    public function get($path, $handler)
    {
        return $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * Thêm route POST
     */
    public function post($path, $handler)
    {
        return $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * Thêm route PUT
     */
    public function put($path, $handler)
    {
        return $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * Thêm route DELETE
     */
    public function delete($path, $handler)
    {
        return $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * Thêm route PATCH
     */
    public function patch($path, $handler)
    {
        return $this->addRoute('PATCH', $path, $handler);
    }
    
    /**
     * Thêm route với method bất kỳ
     */
    public function addRoute($method, $path, $handler)
    {
        $route = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => []
        ];
        
        // Nếu đang trong group, thêm middleware và prefix của group
        if ($this->currentGroup && isset($this->routeGroups[$this->currentGroup])) {
            $group = $this->routeGroups[$this->currentGroup];
            $route['middleware'] = array_merge($group['middleware'], $route['middleware']);
            
            // Thêm prefix vào path
            if (!empty($group['prefix'])) {
                $route['path'] = rtrim($group['prefix'], '/') . '/' . ltrim($path, '/');
                $route['path'] = $route['path'] === '' ? '/' : $route['path'];
            }
        }
        
        $this->routes[] = $route;
        return $this;
    }
    
    /**
     * Tạo nhóm route (áp dụng chung middleware và prefix)
     * 
     * @param array $attributes ['prefix' => '/admin', 'middleware' => ['AuthMiddleware']]
     * @param callable $callback
     */
    public function group($attributes, $callback)
    {
        $groupId = uniqid('group_');
        
        $this->routeGroups[$groupId] = [
            'prefix' => $attributes['prefix'] ?? '',
            'middleware' => $attributes['middleware'] ?? []
        ];
        
        $this->currentGroup = $groupId;
        $callback($this);
        $this->currentGroup = null;
        
        return $this;
    }
    
    /**
     * Thêm middleware cho route cuối cùng hoặc group hiện tại
     */
    public function middleware($middleware)
    {
        if ($this->currentGroup) {
            // Thêm vào group hiện tại
            $this->routeGroups[$this->currentGroup]['middleware'][] = $middleware;
        } else {
            // Thêm vào route cuối cùng
            $lastIndex = count($this->routes) - 1;
            if ($lastIndex >= 0) {
                $this->routes[$lastIndex]['middleware'][] = $middleware;
            }
        }
        return $this;
    }
    
    /**
     * Xử lý request và gọi controller tương ứng
     * 
     * @param string $uri Request URI
     * @param string $method HTTP method
     */
    public function dispatch($uri, $method)
    {
        // Loại bỏ query string
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Loại bỏ prefix /api nếu có
        $uri = preg_replace('#^/api#', '', $uri);
        $uri = $uri === '' ? '/' : $uri;
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            // Chuyển route pattern thành regex
            // {id} -> ([^/]+)
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Bỏ phần tử đầu tiên (toàn bộ match)
                
                // Chạy middleware
                foreach ($route['middleware'] as $middleware) {
                    $result = $this->runMiddleware($middleware);
                    if ($result === false) {
                        return; // Middleware đã trả về response và exit
                    }
                }
                
                // Parse handler (Controller@method)
                $handlerParts = explode('@', $route['handler']);
                if (count($handlerParts) !== 2) {
                    $this->sendError(500, 'Invalid route handler format');
                    return;
                }
                
                $controllerName = "Controllers\\{$handlerParts[0]}";
                $methodName = $handlerParts[1];
                
                if (!class_exists($controllerName)) {
                    $this->sendError(500, "Controller not found: {$controllerName}");
                    return;
                }
                
                $controller = new $controllerName();
                
                if (!method_exists($controller, $methodName)) {
                    $this->sendError(500, "Method not found: {$methodName}");
                    return;
                }
                
                // Gọi controller method với params
                echo call_user_func_array([$controller, $methodName], $matches);
                return;
            }
        }
        
        // Không tìm thấy route
        $this->sendError(404, 'Route not found');
    }
    
    /**
     * Chạy middleware
     * 
     * @param string $middleware Tên middleware (có thể có @method)
     * @return bool
     */
    private function runMiddleware($middleware)
    {
        $middlewareClass = "Middleware\\{$middleware}";
        
        if (strpos($middleware, '@') !== false) {
            // Format: ClassName@method
            $parts = explode('@', $middleware);
            $middlewareClass = "Middleware\\{$parts[0]}";
            $method = $parts[1];
            
            if (class_exists($middlewareClass) && method_exists($middlewareClass, $method)) {
                return $middlewareClass::$method();
            }
        } else {
            // Format: ClassName (gọi handle static)
            if (class_exists($middlewareClass) && method_exists($middlewareClass, 'handle')) {
                return $middlewareClass::handle();
            }
        }
        
        return true;
    }
    
    /**
     * Gửi response lỗi
     */
    private function sendError($code, $message)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }
}