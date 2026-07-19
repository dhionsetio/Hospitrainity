<?php

namespace App\Enums;

enum CurriculumBlockType: string
{
    case Paragraph = 'paragraph';
    case Heading = 'heading';
    case Callout = 'callout';
    case ListItem = 'list_item';
    case DialogueTurn = 'dialogue_turn';
    case SourceTable = 'source_table';
    case ExternalLink = 'external_link';
    case Instruction = 'instruction';
    case ActivityEmbed = 'activity_embed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
