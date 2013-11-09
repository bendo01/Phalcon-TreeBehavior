Phalcon-TreeBehavior
====================

Tree Behavior for PhalconPHP your table must have field : - parent_id type bigint null - lft type bigint null - rght type bigint null your model must provice setter and getter variabel method save this behvior on yourappName/models/Behavour/TreeBehavior.php use it on your model public function initialize() { $this->addBehavior(new TreeBehavior()); }
