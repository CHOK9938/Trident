<?php

declare(strict_types=1);

namespace brokiem\Trident\entity\projectile;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\TakeItemActorPacket;
use pocketmine\Player;

// https://minecraft.fandom.com/wiki/Trident
class Trident extends Projectile {

    public const NETWORK_ID = self::TRIDENT;

    public $height = 0.25;
    public $width = 0.25;
    public $gravity = 0.04;

    protected $damage = 8;

    public function entityBaseTick(int $tickDiff = 1): bool {
        if ($this->closed) {
            return false;
        }

        if ($this->ticksLived > 1200) {
            $this->flagForDespawn();
        }

        return parent::entityBaseTick($tickDiff);
    }

    public function onCollideWithPlayer(Player $player): void {
        if ($this->ticksLived < 10) {
            return;
        }

        $item = ItemFactory::get(ItemIds::TRIDENT, $this->namedtag->getInt("trident_damage", 0));
        $playerInventory = $player->getInventory();
        if ($player->isSurvival()) {
            if (!$playerInventory->canAddItem($item)) {
                return;
            }

            $playerInventory->addItem(clone $item);
        }

        $pk = new TakeItemActorPacket();
        $pk->eid = $player->getId();
        $pk->target = $this->getId();
        $this->server->broadcastPacket($this->getViewers(), $pk);

        $this->flagForDespawn();
    }

    public function onHitEntity(Entity $entityHit, RayTraceResult $hitResult): void {
        if ($entityHit === $this->getOwningEntity()) {
            return;
        }

        $damage = $this->getResultDamage();

        if($damage >= 0){
            if($this->getOwningEntity() === null){
                $ev = new EntityDamageByEntityEvent($this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
            }else{
                $ev = new EntityDamageByChildEntityEvent($this->getOwningEntity(), $this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
            }

            $entityHit->attack($ev);

            if($this->isOnFire()){
                $ev = new EntityCombustByEntityEvent($this, $entityHit, 5);
                $ev->call();
                if(!$ev->isCancelled()){
                    $entityHit->setOnFire($ev->getDuration());
                }
            }
        }

        $nbt = Entity::createBaseNBT($this->add(0.5, 0, 0.5), new Vector3(), -$this->yaw);
        $trident = new Trident($this->getLevelNonNull(), $nbt, $this->getOwningEntity());
        $trident->namedtag->setInt("trident_damage", $this->namedtag->getInt("trident_damage", 0));
        $trident->spawnToAll();

        $this->flagForDespawn();

        $this->getLevelNonNull()->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_ITEM_TRIDENT_HIT);
    }

    public function onHitBlock(Block $blockHit, RayTraceResult $hitResult): void {
        parent::onHitBlock($blockHit, $hitResult);

        $this->getLevelNonNull()->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_ITEM_TRIDENT_HIT_GROUND);
    }
}