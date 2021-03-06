<?php
declare(strict_types=1);

namespace oitq;

use oitq\game\GameStatus;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

class EventListener implements Listener{
	/** @var Loader */
	private $plugin;

	public function __construct(Loader $plugin){
		$this->plugin = $plugin;
	}

	public function handleJoin(PlayerJoinEvent $ev) : void{
		$player = $ev->getPlayer();

		$this->plugin->createGameSession($player); //TODO: Create a session when the player is teleported to the world, rather than when they join.
	}

	public function handleQuit(PlayerQuitEvent $ev) : void{
		$this->plugin->removeGameSession($ev->getPlayer()); //TODO: Remove the session upon world change as well.
	}

	public function handleBreak(BlockBreakEvent $ev) : void{
		if($ev->getPlayer()->getWorld() === $this->plugin->getMap()){
			$ev->setCancelled();
		}
	}

	public function handlePlace(BlockPlaceEvent $ev) : void{
		if($ev->getPlayer()->getWorld() === $this->plugin->getMap()){
			$ev->setCancelled();
		}
	}

	public function handleProjectileHit(ProjectileHitEvent $ev) : void{
		$arrow = $ev->getEntity();
		if($arrow->getWorld() === $this->plugin->getMap() && $arrow instanceof Arrow){
			$arrow->flagForDespawn();
		}
	}

	public function handleRespawn(PlayerRespawnEvent $ev) : void{
		$player = $ev->getPlayer();
		if($this->plugin->getGameSession($player) !== null && $this->plugin->getGameTask()->getGameStatus() > GameStatus::COUNTDOWN){
			$player->teleport($this->plugin->getMap()->getSafeSpawn());
			$this->plugin->sendKit($player);
		}
	}

	public function handleDeath(PlayerDeathEvent $ev) : void{
		$player = $ev->getPlayer();
		$cause = $player->getLastDamageCause();

		if($player->getWorld() === $this->plugin->getMap()){
			$ev->setDrops([]);
			$ev->setDeathMessage("");
			if(in_array($cause->getCause(), [EntityDamageEvent::CAUSE_ENTITY_ATTACK, EntityDamageEvent::CAUSE_PROJECTILE])){
				$damager = $cause->getDamager();
				if($damager instanceof Player && $damager !== $player){
					$damagerSession = $this->plugin->getGameSession($damager);
					if($damagerSession !== null){
						$damager->sendPopup($this->plugin->getMessage("eliminated-player-tip", ["{VICTIM_NAME}" => $player->getDisplayName()]));
						$damagerSession->addElimination();
						$player->getInventory()->addItem(VanillaItems::ARROW());
					}
				}
			}
		}
	}

	public function handleDamage(EntityDamageEvent $ev) : void{
		$cause = $ev->getCause();
		$entity = $ev->getEntity();
		if($entity->getWorld() === $this->plugin->getMap()){
			if($this->plugin->getGameTask()->getGameStatus() !== GameStatus::GAME){
				$ev->setCancelled();
			}

			if($ev instanceof EntityDamageByChildEntityEvent){
				$damager = $ev->getDamager();
				$childEntity = $ev->getChild();
				if($damager instanceof Player && $childEntity instanceof Arrow){
					$entity->attack(new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_PROJECTILE, $entity->getMaxHealth()));
				}
			}
		}
	}
}