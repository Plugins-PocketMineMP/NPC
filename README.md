# NPC

The NPC plugin for [PocketMine-MP](https://github.com/pmmp/PocketMine-MP)

This plugin is designed for **[PocketMine-MP](https://github.com/pmmp/PocketMine-MP)** and may not work with other **spoons or forks**.

[![](https://poggit.pmmp.io/shield.state/NPC)](https://poggit.pmmp.io/p/NPC)

###### it's time to ditch Slapper

|command|usage|
|------|---|
|/npc create [type] [name] [skinPath(optional)] [geometryPath(optional)|Create an entity.
|/npc remove|Remove an entity.
|/npc edit [message/command/scale] [args...]|Edit an entity.
|/npc get|Get an entities.

### Feature

>Doesn't use entity class.
>
>You can create & modify the entities you want.
>
>You can apply your own skin.
>
>Modeling can also be applied.

If you want to apply modeling, you need to put geometryName: "your geometry name" in the json file created with BlockBench.

![](https://raw.githubusercontent.com/alvin0319/NPC/master/images/model.png)

And, to apply modeling, you need to have the gd extension installed in your php binary.