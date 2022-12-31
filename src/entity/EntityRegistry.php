<?php

class PropertyEntity{
	private $class,$type,$name;
	
	function __construct($class, $type, $name){
		$this->class = $class;
		$this->type = $type;
		$this->name = $name;
	}
	
	public function getEntityClass(){
		return $this->class;
	}
	public function getEntityType(){
		return $this->type;
	}
	public function getEntityName(){
		return $this->name;
	}
}

class EntityList{
	public function getEntities(){
		return $this->entities;
	}
	public function addEntity(PropertyEntity $property){
		$this->entities[$property->getEntityType()] = $property;
	}
	public function getEntityFromTypeAndClass($type, $class){
		return isset($this->entities[$type]) && $this->entities[$type]->getEntityClass() === $class ? $this->entities[$type] : 0;
	}
	private $entities = [];
}

class EntityRegistry{
	public static $entityList;
	/*Register all entities*/
	public static function registerEntities(){
		console("[INFO] Registering entities...");
		self::$entityList = new EntityList;
		self::registerEntity(Pig::class);
		self::registerEntity(Chicken::class);
		self::registerEntity(Sheep::class);
		self::registerEntity(Cow::class);
		self::registerEntity(Creeper::class);
		self::registerEntity(PigZombie::class);
		self::registerEntity(Skeleton::class);
		self::registerEntity(Spider::class);
		self::registerEntity(Zombie::class);
		self::registerEntity(Arrow::class);
		self::registerEntity(PrimedTNT::class);
		self::registerEntity(Painting::class);
		self::registerEntity(Minecart::class);
	}
	
	/*Register an Entity*/
	public static function registerEntity($className){
		$class = new \ReflectionClass($className);
		if(is_a($className, Entity::class, true) and !$class->isAbstract()){
			//self::$entityList[$className::TYPE] = $className;
			self::$entityList->addEntity(new PropertyEntity($className::CLASS_TYPE, $className::TYPE, $className));
			//self::$shortNames[$className] = $class->getShortName(); what is this even supposed to do?
			//console("[INFO] Registered a ".$className);
			return true;
		}

		return false;
	}
}