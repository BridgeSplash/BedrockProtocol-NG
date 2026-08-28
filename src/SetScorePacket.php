<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use function count;

class SetScorePacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::SET_SCORE_PACKET;

	public const TYPE_CHANGE = 0;
	public const TYPE_REMOVE = 1;

	public int $type;
	/** @var ScorePacketEntry[] */
	public array $entries = [];

	/**
	 * @generate-create-func
	 * @param ScorePacketEntry[] $entries
	 */
	public static function create(int $type, array $entries) : self{
		$result = new self;
		$result->type = $type;
		$result->entries = $entries;
		return $result;
	}

	/**
	 * Entry actions as sent since 1.26.40, in ordinal order. The value is the name sent alongside the ordinal.
	 *
	 * @var string[]
	 * @phpstan-var array<int, string>
	 */
	private const ACTION_NAMES = [
		self::ACTION_REMOVE => "remove",
		self::ACTION_CHANGE_PLAYER => "changeplayer",
		self::ACTION_CHANGE_ENTITY => "changeentity",
		self::ACTION_CHANGE_FAKE_PLAYER => "changefakeplayer",
	];

	private const ACTION_REMOVE = 0;
	private const ACTION_CHANGE_PLAYER = 1;
	private const ACTION_CHANGE_ENTITY = 2;
	private const ACTION_CHANGE_FAKE_PLAYER = 3;

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			$this->type = Byte::readUnsigned($in);
		}
		for($i = 0, $i2 = VarInt::readUnsignedInt($in); $i < $i2; ++$i){
			$entry = new ScorePacketEntry();

			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				$action = VarInt::readUnsignedInt($in);
				$expectedName = self::ACTION_NAMES[$action] ?? throw new PacketDecodeException("Unknown score packet entry action $action");
				$name = CommonTypes::getString($in);
				if($name !== $expectedName){
					throw new PacketDecodeException("Unexpected inner type $name for score packet entry action $action, expected $expectedName");
				}

				$this->type = $action === self::ACTION_REMOVE ? self::TYPE_REMOVE : self::TYPE_CHANGE;
				$entry->scoreboardId = VarInt::readSignedLong($in);

				if($action === self::ACTION_REMOVE){
					$entry->objectiveName = CommonTypes::readOptional($in, CommonTypes::getString(...)) ?? "";
					$this->entries[] = $entry;
					continue;
				}

				$entry->objectiveName = CommonTypes::getString($in);
				$entry->score = LE::readSignedInt($in);
				if($action === self::ACTION_CHANGE_FAKE_PLAYER){
					$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
					$entry->customName = CommonTypes::getString($in);
				}else{
					$entry->type = $action === self::ACTION_CHANGE_PLAYER ? ScorePacketEntry::TYPE_PLAYER : ScorePacketEntry::TYPE_ENTITY;
					$entry->actorUniqueId = CommonTypes::getActorUniqueId($in);
				}
				$this->entries[] = $entry;
				continue;
			}

			$entry->scoreboardId = VarInt::readSignedLong($in);
			$entry->objectiveName = CommonTypes::getString($in);
			$entry->score = LE::readSignedInt($in);
			if($this->type !== self::TYPE_REMOVE){
				$entry->type = Byte::readUnsigned($in);
				switch($entry->type){
					case ScorePacketEntry::TYPE_PLAYER:
					case ScorePacketEntry::TYPE_ENTITY:
						$entry->actorUniqueId = CommonTypes::getActorUniqueId($in);
						break;
					case ScorePacketEntry::TYPE_FAKE_PLAYER:
						$entry->customName = CommonTypes::getString($in);
						break;
					default:
						throw new PacketDecodeException("Unknown entry type $entry->type");
				}
			}
			$this->entries[] = $entry;
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			Byte::writeUnsigned($out, $this->type);
		}
		VarInt::writeUnsignedInt($out, count($this->entries));
		foreach($this->entries as $entry){
			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				$action = $this->type === self::TYPE_REMOVE ? self::ACTION_REMOVE : match($entry->type){
					ScorePacketEntry::TYPE_PLAYER => self::ACTION_CHANGE_PLAYER,
					ScorePacketEntry::TYPE_ENTITY => self::ACTION_CHANGE_ENTITY,
					ScorePacketEntry::TYPE_FAKE_PLAYER => self::ACTION_CHANGE_FAKE_PLAYER,
					default => throw new \InvalidArgumentException("Unknown entry type $entry->type"),
				};
				VarInt::writeUnsignedInt($out, $action);
				CommonTypes::putString($out, self::ACTION_NAMES[$action]);

				VarInt::writeSignedLong($out, $entry->scoreboardId);

				if($action === self::ACTION_REMOVE){
					CommonTypes::writeOptional($out, $entry->objectiveName, CommonTypes::putString(...));
					continue;
				}

				CommonTypes::putString($out, $entry->objectiveName);
				LE::writeSignedInt($out, $entry->score);
				if($action === self::ACTION_CHANGE_FAKE_PLAYER){
					CommonTypes::putString($out, $entry->customName ?? throw new \InvalidArgumentException("customName must be set for this entry type"));
				}else{
					CommonTypes::putActorUniqueId($out, $entry->actorUniqueId ?? throw new \InvalidArgumentException("actorUniqueId must be set for this entry type"));
				}
				continue;
			}

			VarInt::writeSignedLong($out, $entry->scoreboardId);
			CommonTypes::putString($out, $entry->objectiveName);
			LE::writeSignedInt($out, $entry->score);
			if($this->type !== self::TYPE_REMOVE){
				Byte::writeUnsigned($out, $entry->type);
				switch($entry->type){
					case ScorePacketEntry::TYPE_PLAYER:
					case ScorePacketEntry::TYPE_ENTITY:
						CommonTypes::putActorUniqueId($out, $entry->actorUniqueId);
						break;
					case ScorePacketEntry::TYPE_FAKE_PLAYER:
						CommonTypes::putString($out, $entry->customName);
						break;
					default:
						throw new \InvalidArgumentException("Unknown entry type $entry->type");
				}
			}
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleSetScore($this);
	}
}
