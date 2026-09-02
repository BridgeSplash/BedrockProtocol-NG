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

namespace pocketmine\network\mcpe\protocol\types\skin;

final class PersonaSkinPiece{

	public const PIECE_TYPE_PERSONA_BODY = "persona_body";
	public const PIECE_TYPE_PERSONA_BOTTOM = "persona_bottom";
	public const PIECE_TYPE_PERSONA_EYES = "persona_eyes";
	public const PIECE_TYPE_PERSONA_FACIAL_HAIR = "persona_facial_hair";
	public const PIECE_TYPE_PERSONA_FEET = "persona_feet";
	public const PIECE_TYPE_PERSONA_HAIR = "persona_hair";
	public const PIECE_TYPE_PERSONA_MOUTH = "persona_mouth";
	public const PIECE_TYPE_PERSONA_SKELETON = "persona_skeleton";
	public const PIECE_TYPE_PERSONA_SKIN = "persona_skin";
	public const PIECE_TYPE_PERSONA_TOP = "persona_top";

	/**
	 * Piece types in the order used on the wire since 1.26.40. The position in this array is the ordinal sent for a
	 * persona piece, and the value is the short name sent for a piece tint color.
	 *
	 * @var string[]
	 * @phpstan-var array<string, string>
	 */
	public const PIECE_TYPE_WIRE_NAMES = [
		"persona_unknown" => "unknown",
		self::PIECE_TYPE_PERSONA_SKELETON => "skeleton",
		self::PIECE_TYPE_PERSONA_BODY => "body",
		self::PIECE_TYPE_PERSONA_SKIN => "skin",
		self::PIECE_TYPE_PERSONA_BOTTOM => "bottom",
		self::PIECE_TYPE_PERSONA_FEET => "feet",
		"persona_dress" => "dress",
		self::PIECE_TYPE_PERSONA_TOP => "top",
		"persona_high_pants" => "high_pants",
		"persona_hand" => "hands",
		"persona_outerwear" => "outerwear",
		self::PIECE_TYPE_PERSONA_FACIAL_HAIR => "facialhair",
		self::PIECE_TYPE_PERSONA_MOUTH => "mouth",
		self::PIECE_TYPE_PERSONA_EYES => "eyes",
		self::PIECE_TYPE_PERSONA_HAIR => "hair",
		"persona_hood" => "hood",
		"persona_back" => "back",
		"persona_face_accessory" => "faceaccessory",
		"persona_head" => "head",
		"persona_legs" => "legs",
		"persona_left_leg" => "leftleg",
		"persona_right_leg" => "rightleg",
		"persona_arms" => "arms",
		"persona_left_arm" => "leftarm",
		"persona_right_arm" => "rightarm",
		"persona_capes" => "capes",
		"persona_classic_skin" => "classicskin",
		"persona_emote" => "emote",
		"persona_unsupported" => "unsupported",
	];

	public function __construct(
		private string $pieceId,
		private string $pieceType,
		private string $packId,
		private bool $isDefaultPiece,
		private string $productId
	){}

	public function getPieceId() : string{
		return $this->pieceId;
	}

	public function getPieceType() : string{
		return $this->pieceType;
	}

	public function getPackId() : string{
		return $this->packId;
	}

	public function isDefaultPiece() : bool{
		return $this->isDefaultPiece;
	}

	public function getProductId() : string{
		return $this->productId;
	}
}
