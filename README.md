# prototype

It's a prototype MVC project which includes to explain how it works.

- Index.php : main start file
- Dispatcher.php : main start class
- Core.php : Routing, Analyze, Auth etc
- Controller.php : Core controller which find proper method and run View.
- Model.php : Core Model
- Collection.php : Parent class of Core Model which include magic method to run static method in anywhere
- View.php : Core View


##Structure

This is a MVC framework. In previous projects there wasn't a clear way to develop it as MVC and there was a lot of spaghetti code.  It only had the some controller concepts, so it was quite hard to add new functionalities or maintain the site.


The first step was to make a clear MVC structure. 

This is the main process in creating a common structure. 

```
 - User access -> Routing and Auth -> Controller -> Model -> View
```
I will explain briefly the process below.


###1. Start 

When users first access the website it goes first to 'index.php'
At line number 80~81 on the index.php is the main starting point of this framework.
```
$application = new Dispatcher($db);
$application->run();
```
###2. Process 

**1. Routing and Auth : /app/Dispatcher.php and /app/Core.php**

- It's parent is Core.php which Route and Cache , Detect request type, Analyze request type extra...
- It analyzes the request and runs proper Controllers. 
```
$invoke = new $controller();
return $invoke(static::$request, $props); // invoke controller
```

**2. Controller : /app/Controller.php**

- This is the core controller: every controller has to be inherited.  
 Through __invoke  method, when we call any Controller  every single time it will executes same procedure. 
 it checks if an action exist and executes and runs the View process. so all user requests can do the exact same process. 
```
if($data = $this->{$action}($args)) { // prepare data
    $this->render['data'] = !empty($res) ? $res : $data; // set data
}
$view = new View($this->render, $request, $props);
return $view->render();
```

**2.1 Controller(action in detail) : /app/controller/{Some}Controller.php**

 - Because in the previous version there was no View concept(View class) we couldn't develop simple codes.
 - Key notes
    -> We can't directly access data anymore, only through the Model layer can we maintain data work.
    -> We don't have to control Smarty in an action anymore, only on View layer can it be handled. 
    -> We can get some core vars such as the profile object from the Core layer.

   I will compare the differences: 

  1) Before 
```
public function share() {
    $db = $this->getNewDB();
    $id = $db->sqlInsertInto('{test}', array('id' => '', 'test' => $this->getCurrentUserId()));
    $smarty = $this->getSmarty();
    $user = $this->getApplication()->getCurrentUser();
    $user_object = Users::getUser($user['id']);
    $smarty->assign("_user", $user_object);
    $smarty->assign("location", $this->getPostLocation());
    $smarty->display("posts/share.html");
}
```
 2) After 
```
public function share()
{
    Test::insert(array('id' => '', 'test' => $this->uid));
    $post = Posts::findById($this->request['id']);
    $location = $post->location;
    $this->render['base'] = 'share';
    return compact('location');
}
```
* you can see clear differences

**3. Model : /app/Model.php and /app/Collection.php**
 - All model have to be inherited by Model.php. 
 - Key notes
     -> When we use the model we can choose a return type (array, array of object, object)
     -> Data can be selected in an easier manner Posts::findById($this->request['id'])
     -> We can also use pure SQL $db->run('select * from test')       
     
 - On the Collection.php there is a magic method called __callStatic  Through this we can call like this Modal::action()  

```   
$self = static::_object();
if (method_exists($self,(string)$method)) { // call protected static methods (pure magic)
    return call_user_func_array(array(&$self, $method), $params);
}
```
- Or we can use simple SQL such as Model::getByID('1')
``` 
preg_match('/^findBy(?P<field>\w+)$|^find(?P<type>\w+)By(?P<fields>\w+)$/', $method, $args);
if ($args) {
    $field = self::underscore($args['field'] ? $args['field'] : $args['fields']);
    $type = isset($args['type']) ? $args['type'] : 'first';
    $type[0] = strtolower($type[0]);
    $conditions = array($field => array_shift($params));
    $params = (isset($params[0]) && count($params) === 1) ? $params[0] : $params;
    return self::find($type, compact('conditions') + $params);
}
```
**4. View : /app/View.php and /view/{controller}/{view}.html**

- The main core is Smarty.
- Key notes
  ->  It renders proper view.
  -> Via the user's request the engine will search for the proper view file 
  -> It will render the proper document type(html, json)
  -> It automatically assigns all vars from the return of an action of Controller


##3. Conclusion 

- For over a year now I have been rebuilding this project, building new functions and making substantial improvements to the previous framework. The various changes that I have made have resulted in us being to able to develop MVC structure further.
