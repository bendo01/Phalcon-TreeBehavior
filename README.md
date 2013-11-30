Phalcon-TreeBehavior
====================

Tree Behavior for PhalconPHP your table must have field : 
- parent_id type int null 
- lft type int null
- rght type int null

your model must provice setter and getter variabel method
save this behvior on yourappName/models/Behavour/TreeBehavior.php use it on your model public function initialize() { $this->addBehavior(new TreeBehavior()); }

example how to use it is on https://gist.github.com/bendo01/7090438
