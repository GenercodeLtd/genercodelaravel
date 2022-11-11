<?php
 
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
 
class GenerCodeProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    
        $profileFactory = new \PressToJam\ProfileFactory();
        $this->app->instance("factory", $profileFactory);

     
        $this->app->bind(\GenerCodeOrm\Hooks::class, function($app) {
            $hooks = new \GenerCodeOrm\Hooks($app);
            if ($app->config->hooks) $hooks->loadHooks($app->config->hooks);
            return $hooks;
        });

        $this->app->bind(\GenerCodeOrm\SchemaRepository::class, function($app) {
            $profile = $app->get(\GenerCodeOrm\Profile::class);
            return new \GenerCodeOrm\SchemaRepository($profile->factory);
        });

        $this->app->bind(\GenerCodeOrm\Model::class, function($app) {
            $dbmanager = $app->get(\Illuminate\Database\DatabaseManager::class);
            $schema = $app->make(\GenerCodeOrm\SchemaRepository::class);
            return new \GenerCodeOrm\Model($dbmanager, $schema);
        });

        $this->app->bind(\GenerCodeOrm\Reference::class, function($app) {
            $dbmanager = $app->get(\Illuminate\Database\DatabaseManager::class);
            $schema = $app->make(\GenerCodeOrm\SchemaRepository::class);
            return new \GenerCodeOrm\Reference($app, $dbmanager, $schema);
        });

        $this->app->bind(\GenerCodeOrm\Repository::class, function($app) {
            $dbmanager = $app->get(\Illuminate\Database\DatabaseManager::class);
            $schema = $app->make(\GenerCodeOrm\SchemaRepository::class);
            return new \GenerCodeOrm\Repository($dbmanager, $schema);
        });
        

        $this->app->bind(\GenerCodeOrm\ProfileController::class, function($app) {
            return new \GenerCodeOrm\ProfileController($app);
        });

        $this->app->bind(\GenerCodeOrm\ModelController::class, function($app) {
            return new \GenerCodeOrm\ModelController($app);
        });

        $this->app->bind(\GenerCodeOrm\FileHandler::class, function($app) {
            $file = $app->make(\Illuminate\Filesystem\FilesystemManager::class);
            $prefix = $app->config["filesystems.disks.s3"]['prefix_path'];
            $fileHandler = new \GenerCodeOrm\FileHandler($file, $prefix);
            return $fileHandler;
        });
    }

    public function boot() {
        //copy in routes here
        $prefix = "/gc";
        

        $this->app['router']->options($prefix.'/{routes:.+}', function () {
            return json_encode("");
        });


        $this->app['router']->match(['POST', 'PUT', 'DELETE'], $prefix.'/data/{model}', function(Request $request, $model) {
            $controller = $this->make(\GenerCodeOrm\ModelController::class);
            if (Request::isMethod('post'))  {
                $res = $controller->post($model, new Fluent($request->all()));
            } else if (Request::isMethod('put')) {
                $res = $controller->put($model, new Fluent($request->all()));
            } else {
                $res = $controller->delete($model, new Fluent($request->all()));
            }
            return response(json_encode($res), 200)
            ->header('Content-Type', 'application/json');
        });


        $this->app['router']->put($prefix.'/data/{model}/resort/', function (Request $request, $model) {
            $controller = $this->make(\GenerCodeOrm\ModelController::class);
            $res = $controller->resort($model, new Fluent($request->all()));
            return response(json_encode($res), 200)
            ->header('Content-Type', 'application/json');
        });

        
        $this->app['router']->get($prefix.'/data/{model}[/{state}]', function (Request $request, $model, $state) {
            $state = $state ?? "get";
            $controller = $this->make(\GenerCodeOrm\ModelController::class);
            $res = $controller->get($model, $request->all(), $state);
            return response(json_encode($res), 200)
            ->header('Content-Type', 'application/json');
        });


        $this->app['router']->get($prefix.'/count/{model}', function (Request $request, $model) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $res = $modelController->count($model, new Fluent($request->all()));
            return response(json_encode($res), 200)
            ->header('Content-Type', 'application/json');
        });


        $this->app['router']->get($prefix."/asset/{model}/{field}/{id}", function(Request $request, $model, $field, $id) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $data = $modelController->getAsset($model, $field, $id);
            return response($data, 200);
        });


        $this->app['router']->delete("/asset/{model}/{field}/{id}", function(Request $request, $model, $field, $id) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $data = $modelController->removeAsset($model, $field, $id);
            return response(json_encode($data), 200)
            ->header('Content-Type', 'application/json');
        });


        $this->app['router']->get($prefix."/reference/{model}/{field}[/{id}]", function(Request $request, $model, $field) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $params = $request->all();
            $fluent = null;
            if ($params) $fluent = new Fluent($params);
            $id = $id ?? 0;
            $results = $modelController->reference($model, $field, $id, $fluent); 
            return response(json_encode($results), 200)
            ->header('Content-Type', 'application/json');
        });


        $this->app['router']->get($prefix . "/user/dictionary", function()  {
            $profile = $this->get(\GenerCodeOrm\Profile::class);
            $dict = file_get_contents($this->config->repo_root . "/Dictionary/" . $profile->name . ".json");
            return response(json_encode($dict), 200)
            ->header('Content-Type', 'application/json'); 
        });


        $this->app['router']->get($prefix . "/user/site-map", function() {
            $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
            return response(json_encode($profileController->getSitemap()), 200)
            ->header('Content-Type', 'application/json');
        });

        
        $this->app['router']->post($prefix . "/user/login/{name}", function (Request $request, $name) {
            $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
            $id = $profileController->login($name, new Fluent($request->all()));
            $tokenHandler = $this->make(TokenHandler::class);
            $response = $tokenHandler->save($response, $name, $id);
            $response->setContent(json_encode("success"));
            return $response
            ->header('Content-Type', 'application/json');
        });

        
        $this->app['router']->post($prefix . "/user/login/token/{name}", function (Request $request, $name) {
            $params = new Fluent($request->all());
            $tokenHandler = $this->make(TokenHandler::class);
            return $tokenHandler->loginFromToken($params["token"], $response, $name)
            ->header('Content-Type', 'application/json');
        });
    
    
        $this->app['router']->post($prefix . "/user/anon/{name}", function (Request $request, $name) {
            $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
            $profile = $profileController->ceateAnon($name, new Fluent($request->getParsedBody()));
            $tokenHandler = $this->make(TokenHandler::class);
            $response = $tokenHandler->save($response, $profile);
            $response->setContent(json_encode("success"));
            return $response
            ->header('Content-Type', 'application/json');
        });

            
        $this->app['router']->put($prefix . "/user/switch-tokens", function () {
            $tokenHandler = $this->get(TokenHandler::class);
            $response = $tokenHandler->switchTokens();
            $response->setContent(json_encode("success"));
            return $response
            ->header('Content-Type', 'application/json');
        });
        
        
        $this->app['router']->get($prefix . "/user/check-user", function ()  {
            $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
            return response(json_encode($profileController->checkUser()), 200)
            ->header('Content-Type', 'application/json');
        });

            
        $this->app['router']->post($prefix . "/user/logout", function () {
            $tokenHandler = $this->get(TokenHandler::class);
            $response = $tokenHandler->logout($response);
            $response->setContent(json_encode("success"));
            return $response
            ->header('Content-Type', 'application/json');
        });

    }
}