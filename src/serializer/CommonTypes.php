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

namespace pocketmine\network\mcpe\protocol\serializer;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\command\CommandOriginData;
use pocketmine\network\mcpe\protocol\types\entity\BlockPosMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\CompoundTagMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\FloatMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\ShortMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\Vec3MetadataProperty;
use pocketmine\network\mcpe\protocol\types\FloatGameRule;
use pocketmine\network\mcpe\protocol\types\GameRule;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\network\mcpe\protocol\types\NullGameRule;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\recipe\ComplexAliasItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\ItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\ItemDescriptorType;
use pocketmine\network\mcpe\protocol\types\recipe\MolangItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use pocketmine\network\mcpe\protocol\types\recipe\StringIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\TagItemDescriptor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\network\mcpe\protocol\types\StructureSettings;
use pocketmine\utils\Binary;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function array_keys;
use function array_search;
use function count;
use function dechex;
use function hexdec;
use function is_int;
use function ltrim;
use function preg_match;
use function str_pad;
use function strlen;
use function strrev;
use function substr;
use const STR_PAD_LEFT;

final class CommonTypes{

	/** Meta written for an absent item descriptor since 1.26.40 */
	private const ITEM_DESCRIPTOR_EMPTY_META = 32767;

	private function __construct(){
		//NOOP
	}

	/** @throws DataDecodeException */
	public static function getString(ByteBufferReader $in) : string{
		return $in->readByteArray(VarInt::readUnsignedInt($in));
	}

	public static function putString(ByteBufferWriter $out, string $v) : void{
		VarInt::writeUnsignedInt($out, strlen($v));
		$out->writeByteArray($v);
	}

	/** @throws DataDecodeException */
	public static function getBool(ByteBufferReader $in) : bool{
		return Byte::readUnsigned($in) !== 0;
	}

	public static function putBool(ByteBufferWriter $out, bool $v) : void{
		Byte::writeUnsigned($out, $v ? 1 : 0);
	}

	/** @throws DataDecodeException */
	public static function getUUID(ByteBufferReader $in) : UuidInterface{
		//This is two little-endian longs: bytes 7-0 followed by bytes 15-8
		$p1 = strrev($in->readByteArray(8));
		$p2 = strrev($in->readByteArray(8));
		return Uuid::fromBytes($p1 . $p2);
	}

	public static function putUUID(ByteBufferWriter $out, UuidInterface $uuid) : void{
		$bytes = $uuid->getBytes();
		$out->writeByteArray(strrev(substr($bytes, 0, 8)));
		$out->writeByteArray(strrev(substr($bytes, 8, 8)));
	}

	/**
	 * Reads a color encoded as a #AARRGGBB (or shorter) hex string, as used before 1.26.40.
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	private static function getColorString(ByteBufferReader $in) : string{
		$raw = self::getString($in);
		if(preg_match('/^#[a-fA-F0-9]{1,8}$/', $raw) !== 1){
			throw new PacketDecodeException("Invalid hex color string: '$raw'");
		}

		return $raw;
	}

	/**
	 * Reads an ARGB color as sent since 1.26.40, returning it in the hex string form used by the rest of the API.
	 *
	 * @throws DataDecodeException
	 */
	private static function getColorArgb(ByteBufferReader $in) : string{
		return "#" . str_pad(dechex(LE::readUnsignedInt($in)), 8, "0", STR_PAD_LEFT);
	}

	private static function putColorArgb(ByteBufferWriter $out, string $color) : void{
		$argb = hexdec(ltrim($color, "#"));
		if(!is_int($argb)){
			throw new \InvalidArgumentException("Invalid hex color string: '$color'");
		}

		LE::writeUnsignedInt($out, $argb);
	}

	/** @throws DataDecodeException */
	public static function getSkin(ByteBufferReader $in, int $protocolId) : SkinData{
		$skinId = self::getString($in);
		$skinPlayFabId = self::getString($in);
		$skinResourcePatch = self::getString($in);
		$skinData = self::getSkinImage($in);
		$animations = [];
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$animationCount = VarInt::readUnsignedInt($in);
			for($i = 0; $i < $animationCount; ++$i){
				$skinImage = self::getSkinImage($in);
				$animationType = VarInt::readUnsignedInt($in);
				$animationFrames = LE::readFloat($in);
				$expressionType = VarInt::readUnsignedInt($in);
				$animations[] = new SkinAnimation($skinImage, $animationType, $animationFrames, $expressionType);
			}
		}else{
			$animationCount = LE::readUnsignedInt($in);
			for($i = 0; $i < $animationCount; ++$i){
				$skinImage = self::getSkinImage($in);
				$animationType = LE::readUnsignedInt($in);
				$animationFrames = LE::readFloat($in);
				$expressionType = LE::readUnsignedInt($in);
				$animations[] = new SkinAnimation($skinImage, $animationType, $animationFrames, $expressionType);
			}
		}
		$capeData = self::getSkinImage($in);
		$geometryData = self::getString($in);
		$geometryDataVersion = self::getString($in);
		$animationData = self::getString($in);
		$capeId = self::getString($in);
		$fullSkinId = self::getString($in);
		$personaPieces = [];
		$pieceTintColors = [];
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$pieceTypes = array_keys(PersonaSkinPiece::PIECE_TYPE_WIRE_NAMES);

			$armSizeOrdinal = Byte::readUnsigned($in);
			$armSize = match($armSizeOrdinal){
				0 => SkinData::ARM_SIZE_SLIM,
				1 => SkinData::ARM_SIZE_WIDE,
				default => throw new PacketDecodeException("Unknown arm size ordinal $armSizeOrdinal"),
			};
			$skinColor = self::getColorArgb($in);

			$personaPieceCount = VarInt::readUnsignedInt($in);
			for($i = 0; $i < $personaPieceCount; ++$i){
				$pieceId = self::getString($in);
				$pieceTypeOrdinal = LE::readUnsignedInt($in);
				$pieceType = $pieceTypes[$pieceTypeOrdinal] ?? throw new PacketDecodeException("Unknown persona piece type ordinal $pieceTypeOrdinal");
				$packId = self::getUUID($in)->toString();
				$isDefaultPiece = self::getBool($in);
				$productId = self::getString($in);
				$personaPieces[] = new PersonaSkinPiece($pieceId, $pieceType, $packId, $isDefaultPiece, $productId);
			}

			$pieceTintColorCount = VarInt::readUnsignedInt($in);
			for($i = 0; $i < $pieceTintColorCount; ++$i){
				$wireName = self::getString($in);
				$pieceType = array_search($wireName, PersonaSkinPiece::PIECE_TYPE_WIRE_NAMES, true);
				if($pieceType === false){
					throw new PacketDecodeException("Unknown persona piece tint color type '$wireName'");
				}
				$colors = [];
				for($j = 0; $j < PersonaPieceTintColor::EXPECTED_COLOR_COUNT; ++$j){
					$colors[] = self::getColorArgb($in);
				}
				$pieceTintColors[] = new PersonaPieceTintColor(
					$pieceType,
					$colors
				);
			}
		}else{
			$armSize = self::getString($in);
			$skinColor = self::getColorString($in);
			$personaPieceCount = LE::readUnsignedInt($in);
			for($i = 0; $i < $personaPieceCount; ++$i){
				$pieceId = self::getString($in);
				$pieceType = self::getString($in);
				$packId = self::getString($in);
				$isDefaultPiece = self::getBool($in);
				$productId = self::getString($in);
				$personaPieces[] = new PersonaSkinPiece($pieceId, $pieceType, $packId, $isDefaultPiece, $productId);
			}
			$pieceTintColorCount = LE::readUnsignedInt($in);
			for($i = 0; $i < $pieceTintColorCount; ++$i){
				$pieceType = self::getString($in);
				$colorCount = LE::readUnsignedInt($in);
				$colors = [];
				for($j = 0; $j < $colorCount; ++$j){
					$colors[] = self::getColorString($in);
				}
				$pieceTintColors[] = new PersonaPieceTintColor(
					$pieceType,
					$colors
				);
			}
		}

		$premium = self::getBool($in);
		$persona = self::getBool($in);
		$capeOnClassic = self::getBool($in);
		$isPrimaryUser = self::getBool($in);
		$override = self::getBool($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$verified = self::getString($in) === SkinData::TRUSTED_SKIN_TRUE;
			$profileHash = self::getString($in);
		}else{
			$verified = true;
			$profileHash = "";
		}

		return new SkinData(
			$skinId,
			$skinPlayFabId,
			$skinResourcePatch,
			$skinData,
			$animations,
			$capeData,
			$geometryData,
			$geometryDataVersion,
			$animationData,
			$capeId,
			$fullSkinId,
			$armSize,
			$skinColor,
			$personaPieces,
			$pieceTintColors,
			$verified,
			$premium,
			$persona,
			$capeOnClassic,
			$isPrimaryUser,
			$override,
			$profileHash,
		);
	}

	public static function putSkin(ByteBufferWriter $out, int $protocolId, SkinData $skin) : void{
		self::putString($out, $skin->getSkinId());
		self::putString($out, $skin->getPlayFabId());
		self::putString($out, $skin->getResourcePatch());
		self::putSkinImage($out, $skin->getSkinImage());
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			VarInt::writeUnsignedInt($out, count($skin->getAnimations()));
			foreach($skin->getAnimations() as $animation){
				self::putSkinImage($out, $animation->getImage());
				VarInt::writeUnsignedInt($out, $animation->getType());
				LE::writeFloat($out, $animation->getFrames());
				VarInt::writeUnsignedInt($out, $animation->getExpressionType());
			}
		}else{
			LE::writeUnsignedInt($out, count($skin->getAnimations()));
			foreach($skin->getAnimations() as $animation){
				self::putSkinImage($out, $animation->getImage());
				LE::writeUnsignedInt($out, $animation->getType());
				LE::writeFloat($out, $animation->getFrames());
				LE::writeUnsignedInt($out, $animation->getExpressionType());
			}
		}
		self::putSkinImage($out, $skin->getCapeImage());
		self::putString($out, $skin->getGeometryData());
		self::putString($out, $skin->getGeometryDataEngineVersion());
		self::putString($out, $skin->getAnimationData());
		self::putString($out, $skin->getCapeId());
		self::putString($out, $skin->getFullSkinId());
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$pieceTypes = array_keys(PersonaSkinPiece::PIECE_TYPE_WIRE_NAMES);

			Byte::writeUnsigned($out, $skin->getArmSize() === SkinData::ARM_SIZE_SLIM ? 0 : 1);
			self::putColorArgb($out, $skin->getSkinColor());

			VarInt::writeUnsignedInt($out, count($skin->getPersonaPieces()));
			foreach($skin->getPersonaPieces() as $piece){
				$pieceTypeOrdinal = array_search($piece->getPieceType(), $pieceTypes, true);
				if($pieceTypeOrdinal === false){
					throw new \InvalidArgumentException("Unknown persona piece type '" . $piece->getPieceType() . "'");
				}
				self::putString($out, $piece->getPieceId());
				LE::writeUnsignedInt($out, $pieceTypeOrdinal);
				self::putUUID($out, Uuid::fromString($piece->getPackId()));
				self::putBool($out, $piece->isDefaultPiece());
				self::putString($out, $piece->getProductId());
			}

			VarInt::writeUnsignedInt($out, count($skin->getPieceTintColors()));
			foreach($skin->getPieceTintColors() as $tint){
				$wireName = PersonaSkinPiece::PIECE_TYPE_WIRE_NAMES[$tint->getPieceType()] ??
					throw new \InvalidArgumentException("Unknown persona piece tint color type '" . $tint->getPieceType() . "'");
				$colors = $tint->getColors();
				if(count($colors) !== PersonaPieceTintColor::EXPECTED_COLOR_COUNT){
					throw new \InvalidArgumentException("Expected exactly " . PersonaPieceTintColor::EXPECTED_COLOR_COUNT . " tint colors since 1.26.40");
				}
				self::putString($out, $wireName);
				foreach($colors as $color){
					self::putColorArgb($out, $color);
				}
			}
		}else{
			self::putString($out, $skin->getArmSize());
			self::putString($out, $skin->getSkinColor());
			LE::writeUnsignedInt($out, count($skin->getPersonaPieces()));
			foreach($skin->getPersonaPieces() as $piece){
				self::putString($out, $piece->getPieceId());
				self::putString($out, $piece->getPieceType());
				self::putString($out, $piece->getPackId());
				self::putBool($out, $piece->isDefaultPiece());
				self::putString($out, $piece->getProductId());
			}
			LE::writeUnsignedInt($out, count($skin->getPieceTintColors()));
			foreach($skin->getPieceTintColors() as $tint){
				self::putString($out, $tint->getPieceType());
				LE::writeUnsignedInt($out, count($tint->getColors()));
				foreach($tint->getColors() as $color){
					self::putString($out, $color);
				}
			}
		}
		self::putBool($out, $skin->isPremium());
		self::putBool($out, $skin->isPersona());
		self::putBool($out, $skin->isPersonaCapeOnClassic());
		self::putBool($out, $skin->isPrimaryUser());
		self::putBool($out, $skin->isOverride());
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			self::putString($out, $skin->isVerified() ? SkinData::TRUSTED_SKIN_TRUE : SkinData::TRUSTED_SKIN_FALSE);
			self::putString($out, $skin->getProfileHash());
		}
	}

	/** @throws DataDecodeException */
	private static function getSkinImage(ByteBufferReader $in) : SkinImage{
		$width = LE::readUnsignedInt($in);
		$height = LE::readUnsignedInt($in);
		$data = self::getString($in);
		try{
			return new SkinImage($height, $width, $data);
		}catch(\InvalidArgumentException $e){
			throw new PacketDecodeException($e->getMessage(), 0, $e);
		}
	}

	private static function putSkinImage(ByteBufferWriter $out, SkinImage $image) : void{
		LE::writeUnsignedInt($out, $image->getWidth());
		LE::writeUnsignedInt($out, $image->getHeight());
		self::putString($out, $image->getData());
	}

	/**
	 * @return int[]
	 * @phpstan-return array{0: int, 1: int, 2: int}
	 * @throws DataDecodeException
	 */
	private static function getItemStackHeader(ByteBufferReader $in, int $protocolId) : array{
		$id = VarInt::readSignedInt($in);
		if($id === 0 && $protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			return [0, 0, 0];
		}

		$count = LE::readUnsignedShort($in);
		$meta = VarInt::readUnsignedInt($in);

		return [$id, $count, $meta];
	}

	private static function putItemStackHeader(ByteBufferWriter $out, int $protocolId, ItemStack $itemStack) : bool{
		if($itemStack->getId() === 0 && $protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			VarInt::writeSignedInt($out, 0);
			return false;
		}

		VarInt::writeSignedInt($out, $itemStack->getId());
		LE::writeUnsignedShort($out, $itemStack->getCount());
		VarInt::writeUnsignedInt($out, $itemStack->getMeta());

		return true;
	}

	/** @throws DataDecodeException */
	private static function getItemStackFooter(ByteBufferReader $in, int $id, int $meta, int $count) : ItemStack{
		$blockRuntimeId = VarInt::readSignedInt($in);
		$rawExtraData = self::getString($in);

		return new ItemStack($id, $meta, $count, $blockRuntimeId, $rawExtraData);
	}

	private static function putItemStackFooter(ByteBufferWriter $out, ItemStack $itemStack) : void{
		VarInt::writeSignedInt($out, $itemStack->getBlockRuntimeId());
		self::putString($out, $itemStack->getRawExtraData());
	}

	/**
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	public static function getItemStackWithoutStackId(ByteBufferReader $in, int $protocolId) : ItemStack{
		[$id, $count, $meta] = self::getItemStackHeader($in, $protocolId);

		return $id !== 0 || $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ?
			self::getItemStackFooter($in, $id, $meta, $count) :
			ItemStack::null();
	}

	public static function putItemStackWithoutStackId(ByteBufferWriter $out, int $protocolId, ItemStack $itemStack) : void{
		if(self::putItemStackHeader($out, $protocolId, $itemStack)){
			self::putItemStackFooter($out, $itemStack);
		}
	}

	/** @throws DataDecodeException */
	public static function getItemStackWrapper(ByteBufferReader $in, int $protocolId) : ItemStackWrapper{
		[$id, $count, $meta] = self::getItemStackHeader($in, $protocolId);
		if($id === 0 && $protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			return new ItemStackWrapper(0, ItemStack::null());
		}

		$hasNetId = self::getBool($in);
		$stackId = $hasNetId ? self::readServerItemStackId($in) : 0;

		$itemStack = self::getItemStackFooter($in, $id, $meta, $count);

		return new ItemStackWrapper($stackId, $itemStack);
	}

	public static function putItemStackWrapper(ByteBufferWriter $out, int $protocolId, ItemStackWrapper $itemStackWrapper) : void{
		$itemStack = $itemStackWrapper->getItemStack();
		if(self::putItemStackHeader($out, $protocolId, $itemStack)){
			$hasNetId = $itemStackWrapper->getStackId() !== 0;
			self::putBool($out, $hasNetId);
			if($hasNetId){
				self::writeServerItemStackId($out, $itemStackWrapper->getStackId());
			}

			self::putItemStackFooter($out, $itemStack);
		}
	}

	public static function getNetworkItemStackDescriptor(ByteBufferReader $in, int $protocolId) : ItemStackWrapper{
		$id = LE::readSignedShort($in);
		$count = LE::readUnsignedShort($in);
		$meta = VarInt::readUnsignedInt($in);

		$hasNetId = self::getBool($in);
		if($hasNetId){
			$variant = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? 0 : VarInt::readUnsignedInt($in);
			$stackId = VarInt::readSignedInt($in);
		}else{
			$variant = 0;
			$stackId = 0;
		}

		$blockRuntimeId = VarInt::readUnsignedInt($in);
		$rawExtraData = self::getString($in);

		return new ItemStackWrapper($stackId, new ItemStack($id, $meta, $count, $blockRuntimeId, $rawExtraData), $variant);
	}

	public static function putNetworkItemStackDescriptor(ByteBufferWriter $out, int $protocolId, ItemStackWrapper $itemStackWrapper) : void{
		LE::writeSignedShort($out, $itemStackWrapper->getItemStack()->getId());
		LE::writeUnsignedShort($out, $itemStackWrapper->getItemStack()->getCount());
		VarInt::writeUnsignedInt($out, $itemStackWrapper->getItemStack()->getMeta());

		self::putBool($out, $hasNetId = $itemStackWrapper->getStackId() !== 0);
		if($hasNetId){
			if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
				VarInt::writeUnsignedInt($out, $itemStackWrapper->getStackIdVariant());
			}
			VarInt::writeSignedInt($out, $itemStackWrapper->getStackId());
		}

		VarInt::writeUnsignedInt($out, $itemStackWrapper->getItemStack()->getBlockRuntimeId());
		self::putString($out, $itemStackWrapper->getItemStack()->getRawExtraData());
	}

	/**
	 * Reads an item descriptor in the format used by recipe ingredients.
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	private static function getItemDescriptorMess(ByteBufferReader $in, int $protocolId) : ?ItemDescriptor{
		if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			$descriptorType = Byte::readUnsigned($in);

			return match($descriptorType){
				ItemDescriptorType::INT_ID_META => IntIdMetaItemDescriptor::read($in, $protocolId),
				ItemDescriptorType::STRING_ID_META => StringIdMetaItemDescriptor::read($in, $protocolId),
				ItemDescriptorType::TAG => TagItemDescriptor::read($in, $protocolId),
				ItemDescriptorType::MOLANG => MolangItemDescriptor::read($in, $protocolId),
				ItemDescriptorType::COMPLEX_ALIAS => ComplexAliasItemDescriptor::read($in, $protocolId),
				default => null
			};
		}

		$present = Byte::readUnsigned($in);
		if($present === 0){
			$meta = VarInt::readSignedInt($in);
			if($meta !== self::ITEM_DESCRIPTOR_EMPTY_META){
				throw new PacketDecodeException("Expected meta " . self::ITEM_DESCRIPTOR_EMPTY_META . " for empty item descriptor, got $meta");
			}
			return null;
		}elseif($present !== 1){
			throw new PacketDecodeException("Expected 0 or 1 for item descriptor variant, got $present");
		}

		$name = self::getString($in);
		$descriptorType = array_search($name, ItemDescriptorType::NAMES, true);
		if($descriptorType === false){
			throw new PacketDecodeException("Unknown item descriptor type '$name'");
		}

		return self::getItemDescriptorBody($in, $protocolId, $descriptorType, false);
	}

	private static function putItemDescriptorMess(ByteBufferWriter $out, int $protocolId, ?ItemDescriptor $descriptor) : void{
		if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			Byte::writeUnsigned($out, $descriptor?->getTypeId() ?? 0);
			$descriptor?->write($out, $protocolId);
			return;
		}

		if($descriptor === null){
			Byte::writeUnsigned($out, 0);
			VarInt::writeSignedInt($out, self::ITEM_DESCRIPTOR_EMPTY_META);
			return;
		}

		$descriptorType = $descriptor->getTypeId();
		self::checkItemDescriptorSupported($descriptorType);
		Byte::writeUnsigned($out, 1);
		self::putString($out, ItemDescriptorType::NAMES[$descriptorType]);
		$descriptor->write($out, $protocolId);
	}

	/**
	 * Reads an item descriptor in the format used by item stack requests.
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	public static function getItemDescriptorNormal(ByteBufferReader $in, int $protocolId) : ?ItemDescriptor{
		if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			return self::getItemDescriptorMess($in, $protocolId);
		}

		$descriptorTypeOrdinal = VarInt::readUnsignedInt($in);
		$innerTypeOrdinal = Byte::readUnsigned($in);
		if($descriptorTypeOrdinal !== $innerTypeOrdinal){
			throw new PacketDecodeException("Item descriptor type mismatch: outer type $descriptorTypeOrdinal, inner type $innerTypeOrdinal");
		}
		if($descriptorTypeOrdinal === ItemDescriptorType::EMPTY_ORDINAL){
			return null;
		}

		$descriptorType = array_search($descriptorTypeOrdinal, ItemDescriptorType::ORDINALS, true);
		if($descriptorType === false){
			throw new PacketDecodeException("Unknown item descriptor type ordinal $descriptorTypeOrdinal");
		}

		return self::getItemDescriptorBody($in, $protocolId, $descriptorType, true);
	}

	public static function putItemDescriptorNormal(ByteBufferWriter $out, int $protocolId, ?ItemDescriptor $descriptor) : void{
		if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			self::putItemDescriptorMess($out, $protocolId, $descriptor);
			return;
		}

		if($descriptor === null){
			VarInt::writeUnsignedInt($out, ItemDescriptorType::EMPTY_ORDINAL);
			Byte::writeUnsigned($out, ItemDescriptorType::EMPTY_ORDINAL);
			return;
		}

		$descriptorType = $descriptor->getTypeId();
		self::checkItemDescriptorSupported($descriptorType);
		$ordinal = ItemDescriptorType::ORDINALS[$descriptorType];
		VarInt::writeUnsignedInt($out, $ordinal);
		Byte::writeUnsigned($out, $ordinal);
		if($descriptor instanceof TagItemDescriptor){
			//the meta isn't sent in this format
			$descriptor->writeTagOnly($out);
		}else{
			$descriptor->write($out, $protocolId);
		}
	}

	/**
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	private static function getItemDescriptorBody(ByteBufferReader $in, int $protocolId, int $descriptorType, bool $tagOnly) : ItemDescriptor{
		return match($descriptorType){
			ItemDescriptorType::STRING_ID_META => StringIdMetaItemDescriptor::read($in, $protocolId),
			ItemDescriptorType::TAG => $tagOnly ? TagItemDescriptor::readTagOnly($in) : TagItemDescriptor::read($in, $protocolId),
			ItemDescriptorType::MOLANG => MolangItemDescriptor::read($in, $protocolId),
			default => throw new PacketDecodeException("Item descriptor type $descriptorType is not supported since 1.26.40"),
		};
	}

	private static function checkItemDescriptorSupported(int $descriptorType) : void{
		if($descriptorType === ItemDescriptorType::INT_ID_META || $descriptorType === ItemDescriptorType::COMPLEX_ALIAS){
			throw new \InvalidArgumentException("Item descriptor type $descriptorType cannot be sent since 1.26.40");
		}
	}

	/** @throws DataDecodeException */
	public static function getRecipeIngredient(ByteBufferReader $in, int $protocolId) : RecipeIngredient{
		$descriptor = self::getItemDescriptorMess($in, $protocolId);
		$count = VarInt::readSignedInt($in);

		return new RecipeIngredient($descriptor, $count);
	}

	public static function putRecipeIngredient(ByteBufferWriter $out, int $protocolId, RecipeIngredient $ingredient) : void{
		self::putItemDescriptorMess($out, $protocolId, $ingredient->getDescriptor());
		VarInt::writeSignedInt($out, $ingredient->getCount());
	}

	/** @throws DataDecodeException */
	public static function getStackRequestIngredient(ByteBufferReader $in, int $protocolId) : RecipeIngredient{
		$descriptor = self::getItemDescriptorNormal($in, $protocolId);
		$count = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? LE::readUnsignedShort($in) : VarInt::readSignedInt($in);

		return new RecipeIngredient($descriptor, $count);
	}

	public static function putStackRequestIngredient(ByteBufferWriter $out, int $protocolId, RecipeIngredient $ingredient) : void{
		self::putItemDescriptorNormal($out, $protocolId, $ingredient->getDescriptor());
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			LE::writeUnsignedShort($out, $ingredient->getCount());
		}else{
			VarInt::writeSignedInt($out, $ingredient->getCount());
		}
	}

	/**
	 * Decodes entity metadata from the stream.
	 *
	 * @return MetadataProperty[]
	 * @phpstan-return array<int, MetadataProperty>
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	public static function getEntityMetadata(ByteBufferReader $in, int $protocolId) : array{
		$count = VarInt::readUnsignedInt($in);
		$data = [];
		for($i = 0; $i < $count; ++$i){
			$key = VarInt::readUnsignedInt($in);
			$type = VarInt::readUnsignedInt($in);
			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				$innerType = Byte::readUnsigned($in);
				if($type !== $innerType){
					throw new PacketDecodeException("Entity metadata type mismatch: expected $type, got $innerType");
				}
			}

			$data[$key] = self::readMetadataProperty($in, $type);
		}

		return $data;
	}

	/** @throws DataDecodeException */
	private static function readMetadataProperty(ByteBufferReader $in, int $type) : MetadataProperty{
		return match($type){
			ByteMetadataProperty::ID => ByteMetadataProperty::read($in),
			ShortMetadataProperty::ID => ShortMetadataProperty::read($in),
			IntMetadataProperty::ID => IntMetadataProperty::read($in),
			FloatMetadataProperty::ID => FloatMetadataProperty::read($in),
			StringMetadataProperty::ID => StringMetadataProperty::read($in),
			CompoundTagMetadataProperty::ID => CompoundTagMetadataProperty::read($in),
			BlockPosMetadataProperty::ID => BlockPosMetadataProperty::read($in),
			LongMetadataProperty::ID => LongMetadataProperty::read($in),
			Vec3MetadataProperty::ID => Vec3MetadataProperty::read($in),
			default => throw new PacketDecodeException("Unknown entity metadata type " . $type),
		};
	}

	/**
	 * Writes entity metadata to the packet buffer.
	 *
	 * @param MetadataProperty[] $metadata
	 *
	 * @phpstan-param array<int, MetadataProperty> $metadata
	 */
	public static function putEntityMetadata(ByteBufferWriter $out, int $protocolId, array $metadata) : void{
		VarInt::writeUnsignedInt($out, count($metadata));
		foreach($metadata as $key => $d){
			VarInt::writeUnsignedInt($out, $key);
			VarInt::writeUnsignedInt($out, $d->getTypeId());
			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				Byte::writeUnsigned($out, $d->getTypeId());
			}
			$d->write($out);
		}
	}

	/** @throws DataDecodeException */
	public static function getActorUniqueId(ByteBufferReader $in) : int{
		return VarInt::readSignedLong($in);
	}

	public static function putActorUniqueId(ByteBufferWriter $out, int $eid) : void{
		VarInt::writeSignedLong($out, $eid);
	}

	/** @throws DataDecodeException */
	public static function getActorRuntimeId(ByteBufferReader $in) : int{
		return VarInt::readUnsignedLong($in);
	}

	public static function putActorRuntimeId(ByteBufferWriter $out, int $eid) : void{
		VarInt::writeUnsignedLong($out, $eid);
	}

	/**
	 * Reads a block position
	 *
	 * @throws DataDecodeException
	 */
	public static function getBlockPosition(ByteBufferReader $in, bool $signedY = true) : BlockPosition{
		$x = VarInt::readSignedInt($in);
		$y = $signedY ? VarInt::readSignedInt($in) : Binary::signInt(VarInt::readUnsignedInt($in));
		$z = VarInt::readSignedInt($in);
		return new BlockPosition($x, $y, $z);
	}

	/**
	 * Writes a block position
	 */
	public static function putBlockPosition(ByteBufferWriter $out, BlockPosition $blockPosition, bool $signedY = true) : void{
		VarInt::writeSignedInt($out, $blockPosition->getX());
		if($signedY){
			VarInt::writeSignedInt($out, $blockPosition->getY());
		}else{
			VarInt::writeUnsignedInt($out, Binary::unsignInt($blockPosition->getY()));
		}
		VarInt::writeSignedInt($out, $blockPosition->getZ());
	}

	/**
	 * Reads a floating-point Vector3 object with coordinates rounded to 4 decimal places.
	 *
	 * @throws DataDecodeException
	 */
	public static function getVector3(ByteBufferReader $in) : Vector3{
		$x = LE::readFloat($in);
		$y = LE::readFloat($in);
		$z = LE::readFloat($in);
		return new Vector3($x, $y, $z);
	}

	/**
	 * Reads a floating-point Vector2 object with coordinates rounded to 4 decimal places.
	 *
	 * @throws DataDecodeException
	 */
	public static function getVector2(ByteBufferReader $in) : Vector2{
		$x = LE::readFloat($in);
		$y = LE::readFloat($in);
		return new Vector2($x, $y);
	}

	/**
	 * Writes a floating-point Vector3 object, or 3x zero if null is given.
	 *
	 * Note: ONLY use this where it is reasonable to allow not specifying the vector.
	 * For all other purposes, use the non-nullable version.
	 *
	 * @see CommonTypes::putVector3()
	 */
	public static function putVector3Nullable(ByteBufferWriter $out, ?Vector3 $vector) : void{
		if($vector !== null){
			self::putVector3($out, $vector);
		}else{
			LE::writeFloat($out, 0.0);
			LE::writeFloat($out, 0.0);
			LE::writeFloat($out, 0.0);
		}
	}

	/**
	 * Writes a floating-point Vector3 object
	 */
	public static function putVector3(ByteBufferWriter $out, Vector3 $vector) : void{
		LE::writeFloat($out, $vector->x);
		LE::writeFloat($out, $vector->y);
		LE::writeFloat($out, $vector->z);
	}

	/**
	 * Writes a floating-point Vector2 object
	 */
	public static function putVector2(ByteBufferWriter $out, Vector2 $vector2) : void{
		LE::writeFloat($out, $vector2->x);
		LE::writeFloat($out, $vector2->y);
	}

	/** @throws DataDecodeException */
	public static function getRotationByte(ByteBufferReader $in) : float{
		return Byte::readUnsigned($in) * (360 / 256);
	}

	public static function putRotationByte(ByteBufferWriter $out, float $rotation) : void{
		Byte::writeUnsigned($out, (int) ($rotation / (360 / 256)));
	}

	/** @throws DataDecodeException */
	private static function readGameRule(ByteBufferReader $in, int $protocolId, int $type, bool $isPlayerModifiable, bool $isStartGame) : GameRule{
		return match($type){
			NullGameRule::ID => NullGameRule::decode($in, $protocolId, $isPlayerModifiable),
			BoolGameRule::ID => BoolGameRule::decode($in, $protocolId, $isPlayerModifiable),
			IntGameRule::ID => IntGameRule::decode($in, $protocolId, $isPlayerModifiable, $isStartGame),
			FloatGameRule::ID => FloatGameRule::decode($in, $protocolId, $isPlayerModifiable),
			default => throw new PacketDecodeException("Unknown gamerule type $type"),
		};
	}

	/**
	 * Reads gamerules
	 *
	 * @return GameRule[] game rule name => value
	 * @phpstan-return array<string, GameRule>
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	public static function getGameRules(ByteBufferReader $in, int $protocolId, bool $isStartGame) : array{
		$count = VarInt::readUnsignedInt($in);
		$rules = [];
		for($i = 0; $i < $count; ++$i){
			$name = self::getString($in);
			$isPlayerModifiable = self::getBool($in);
			$type = VarInt::readUnsignedInt($in);
			$rules[$name] = self::readGameRule($in, $protocolId, $type, $isPlayerModifiable, $isStartGame);
		}

		return $rules;
	}

	/**
	 * Writes a gamerule array
	 *
	 * @param GameRule[] $rules
	 * @phpstan-param array<string, GameRule> $rules
	 */
	public static function putGameRules(ByteBufferWriter $out, int $protocolId, array $rules, bool $isStartGame) : void{
		VarInt::writeUnsignedInt($out, count($rules));
		foreach($rules as $name => $rule){
			self::putString($out, $name);
			self::putBool($out, $rule->isPlayerModifiable());
			VarInt::writeUnsignedInt($out, $rule->getTypeId());
			$rule->encode($out, $protocolId, $isStartGame);
		}
	}

	/** @throws DataDecodeException */
	public static function getEntityLink(ByteBufferReader $in, int $protocolId) : EntityLink{
		$fromActorUniqueId = self::getActorUniqueId($in);
		$toActorUniqueId = self::getActorUniqueId($in);
		$type = Byte::readUnsigned($in);
		$immediate = self::getBool($in);
		$causedByRider = self::getBool($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_20){
			$vehicleAngularVelocity = LE::readFloat($in);
		}
		return new EntityLink($fromActorUniqueId, $toActorUniqueId, $type, $immediate, $causedByRider, $vehicleAngularVelocity ?? 0);
	}

	public static function putEntityLink(ByteBufferWriter $out, int $protocolId, EntityLink $link) : void{
		self::putActorUniqueId($out, $link->fromActorUniqueId);
		self::putActorUniqueId($out, $link->toActorUniqueId);
		Byte::writeUnsigned($out, $link->type);
		self::putBool($out, $link->immediate);
		self::putBool($out, $link->causedByRider);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_20){
			LE::writeFloat($out, $link->vehicleAngularVelocity);
		}
	}

	/** @throws DataDecodeException */
	public static function getCommandOriginData(ByteBufferReader $in, int $protocolId) : CommandOriginData{
		$result = new CommandOriginData();

		$result->type = $protocolId >= ProtocolInfo::PROTOCOL_1_21_130 ? CommonTypes::getString($in) : CommandOriginData::getTypeFromId(VarInt::readUnsignedInt($in));
		$result->uuid = self::getUUID($in);
		$result->requestId = self::getString($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_130){
			$result->playerActorUniqueId = LE::readSignedLong($in);
		}elseif($result->type === CommandOriginData::ORIGIN_DEV_CONSOLE or $result->type === CommandOriginData::ORIGIN_TEST){
			$result->playerActorUniqueId = VarInt::readSignedLong($in);
		}

		return $result;
	}

	public static function putCommandOriginData(ByteBufferWriter $out, CommandOriginData $data, int $protocolId) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_130){
			self::putString($out, $data->type);
		}else{
			VarInt::writeUnsignedInt($out, CommandOriginData::getIdFromType($data->type));
		}
		self::putUUID($out, $data->uuid);
		self::putString($out, $data->requestId);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_130){
			LE::writeSignedLong($out, $data->playerActorUniqueId);
		}elseif($data->type === CommandOriginData::ORIGIN_DEV_CONSOLE or $data->type === CommandOriginData::ORIGIN_TEST){
			VarInt::writeSignedLong($out, $data->playerActorUniqueId);
		}
	}

	/** @throws DataDecodeException */
	public static function getStructureSettings(ByteBufferReader $in, int $protocolId) : StructureSettings{
		$result = new StructureSettings();

		$result->paletteName = self::getString($in);

		$result->ignoreEntities = self::getBool($in);
		$result->ignoreBlocks = self::getBool($in);
		$result->allowNonTickingChunks = self::getBool($in);

		$result->dimensions = self::getBlockPosition($in, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
		$result->offset = self::getBlockPosition($in, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);

		$result->lastTouchedByPlayerID = self::getActorUniqueId($in);
		$result->rotation = Byte::readUnsigned($in);
		$result->mirror = Byte::readUnsigned($in);
		$result->animationMode = Byte::readUnsigned($in);
		$result->animationSeconds = LE::readFloat($in);
		$result->integrityValue = LE::readFloat($in);
		$result->integritySeed = LE::readUnsignedInt($in);
		$result->pivot = self::getVector3($in);

		return $result;
	}

	public static function putStructureSettings(ByteBufferWriter $out, StructureSettings $structureSettings, int $protocolId) : void{
		self::putString($out, $structureSettings->paletteName);

		self::putBool($out, $structureSettings->ignoreEntities);
		self::putBool($out, $structureSettings->ignoreBlocks);
		self::putBool($out, $structureSettings->allowNonTickingChunks);

		self::putBlockPosition($out, $structureSettings->dimensions, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
		self::putBlockPosition($out, $structureSettings->offset, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);

		self::putActorUniqueId($out, $structureSettings->lastTouchedByPlayerID);
		Byte::writeUnsigned($out, $structureSettings->rotation);
		Byte::writeUnsigned($out, $structureSettings->mirror);
		Byte::writeUnsigned($out, $structureSettings->animationMode);
		LE::writeFloat($out, $structureSettings->animationSeconds);
		LE::writeFloat($out, $structureSettings->integrityValue);
		LE::writeUnsignedInt($out, $structureSettings->integritySeed);
		self::putVector3($out, $structureSettings->pivot);
	}

	/** @throws DataDecodeException */
	public static function getStructureEditorData(ByteBufferReader $in, int $protocolId) : StructureEditorData{
		$result = new StructureEditorData();

		$result->structureName = self::getString($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$result->filteredStructureName = self::readOptional($in, self::getString(...));
		}elseif($protocolId >= ProtocolInfo::PROTOCOL_1_21_60){
			$result->filteredStructureName = self::getString($in);
		}
		$result->structureDataField = self::getString($in);

		$result->includePlayers = self::getBool($in);
		$result->showBoundingBox = self::getBool($in);

		$result->structureBlockType = VarInt::readSignedInt($in);
		$result->structureSettings = self::getStructureSettings($in, $protocolId);
		$result->structureRedstoneSaveMode = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? Byte::readUnsigned($in) : VarInt::readSignedInt($in);

		return $result;
	}

	public static function putStructureEditorData(ByteBufferWriter $out, int $protocolId, StructureEditorData $structureEditorData) : void{
		self::putString($out, $structureEditorData->structureName);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			self::writeOptional($out, $structureEditorData->filteredStructureName, self::putString(...));
		}elseif($protocolId >= ProtocolInfo::PROTOCOL_1_21_60){
			self::putString($out, $structureEditorData->filteredStructureName ?? "");
		}
		self::putString($out, $structureEditorData->structureDataField);

		self::putBool($out, $structureEditorData->includePlayers);
		self::putBool($out, $structureEditorData->showBoundingBox);

		VarInt::writeSignedInt($out, $structureEditorData->structureBlockType);
		self::putStructureSettings($out, $structureEditorData->structureSettings, $protocolId);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			Byte::writeUnsigned($out, $structureEditorData->structureRedstoneSaveMode);
		}else{
			VarInt::writeSignedInt($out, $structureEditorData->structureRedstoneSaveMode);
		}
	}

	/** @throws PacketDecodeException */
	public static function getNbtRoot(ByteBufferReader $in) : TreeRoot{
		$offset = $in->getOffset();
		try{
			return (new NetworkNbtSerializer())->read($in->getData(), $offset, 512);
		}catch(NbtDataException $e){
			throw PacketDecodeException::wrap($e, "Failed decoding NBT root");
		}finally{
			$in->setOffset($offset);
		}
	}

	public static function getNbtCompoundRoot(ByteBufferReader $in) : CompoundTag{
		try{
			return self::getNbtRoot($in)->mustGetCompoundTag();
		}catch(NbtDataException $e){
			throw PacketDecodeException::wrap($e, "Expected TAG_Compound NBT root");
		}
	}

	/** @throws DataDecodeException */
	public static function readRecipeNetId(ByteBufferReader $in) : int{
		return VarInt::readUnsignedInt($in);
	}

	public static function writeRecipeNetId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeUnsignedInt($out, $id);
	}

	/** @throws DataDecodeException */
	public static function readCreativeItemNetId(ByteBufferReader $in) : int{
		return VarInt::readUnsignedInt($in);
	}

	public static function writeCreativeItemNetId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeUnsignedInt($out, $id);
	}

	/**
	 * This is a union of ItemStackRequestId, LegacyItemStackRequestId, and ServerItemStackId, used in serverbound
	 * packets to allow the client to refer to server known items, or items which may have been modified by a previous
	 * as-yet unacknowledged request from the client.
	 *
	 * - Server itemstack ID is positive
	 * - InventoryTransaction "legacy" request ID is negative and even
	 * - ItemStackRequest request ID is negative and odd
	 * - 0 refers to an empty itemstack (air)
	 *
	 * @throws DataDecodeException
	 */
	public static function readItemStackNetIdVariant(ByteBufferReader $in, int $protocolId) : int{
		return $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? LE::readSignedInt($in) : VarInt::readSignedInt($in);
	}

	/**
	 * This is a union of ItemStackRequestId, LegacyItemStackRequestId, and ServerItemStackId, used in serverbound
	 * packets to allow the client to refer to server known items, or items which may have been modified by a previous
	 * as-yet unacknowledged request from the client.
	 */
	public static function writeItemStackNetIdVariant(ByteBufferWriter $out, int $protocolId, int $id) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			LE::writeSignedInt($out, $id);
		}else{
			VarInt::writeSignedInt($out, $id);
		}
	}

	/** @throws DataDecodeException */
	public static function readItemStackRequestId(ByteBufferReader $in) : int{
		return VarInt::readSignedInt($in);
	}

	public static function writeItemStackRequestId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeSignedInt($out, $id);
	}

	/** @throws DataDecodeException */
	public static function readLegacyItemStackRequestId(ByteBufferReader $in) : int{
		return VarInt::readSignedInt($in);
	}

	public static function writeLegacyItemStackRequestId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeSignedInt($out, $id);
	}

	/** @throws DataDecodeException */
	public static function readServerItemStackId(ByteBufferReader $in) : int{
		return VarInt::readSignedInt($in);
	}

	public static function writeServerItemStackId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeSignedInt($out, $id);
	}

	/**
	 * @phpstan-template T
	 * @phpstan-param \Closure(ByteBufferReader) : (T|null) $reader
	 * @phpstan-return T|null
	 * @throws DataDecodeException
	 */
	public static function readOptional(ByteBufferReader $in, \Closure $reader) : mixed{
		if(self::getBool($in)){
			return $reader($in);
		}
		return null;
	}

	/**
	 * @phpstan-template T
	 * @phpstan-param T|null $value
	 * @phpstan-param \Closure(ByteBufferWriter, T) : void $writer
	 */
	public static function writeOptional(ByteBufferWriter $out, mixed $value, \Closure $writer) : void{
		if($value !== null){
			self::putBool($out, true);
			$writer($out, $value);
		}else{
			self::putBool($out, false);
		}
	}
}
