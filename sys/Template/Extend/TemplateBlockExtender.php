<?php
/**
 * Created by Forsaken
 * Date: 13.03.15
 * Time: 21:23
 */

class Template_Extend_TemplateBlockExtender {
    static public function extendTemplate($filenameBase, $filenameExt, $deleteExt = true) {
        if (!file_exists($filenameBase)) {
            return false;
        }
        if (($filenameExt === null) || !file_exists($filenameExt)) {
            //return self::removeBlocks($filenameBase);
            return true;
        }
        $pregBlockStart = '/\{startblock\(' . '([^\)]+)' . '\)\}/';
        $templateCodeBase = file_get_contents($filenameBase);
        $arBlocks = self::readBlocks($templateCodeBase, $pregBlockStart);
        $templateCodeExt = file_get_contents($filenameExt);
        $arBlocks = self::readBlocks($templateCodeExt, $pregBlockStart, $arBlocks);
        $templateCodeBase = self::replaceBlocks($templateCodeBase, $arBlocks);
        // Update extended template
        file_put_contents($filenameBase, $templateCodeBase);
        // Delete extension template
        if ($deleteExt) {
            unlink($filenameExt);
        }
        return true;
    }

    static public function extendTemplateCode(&$code, &$arBlocks = array()) {
        $pregBlockStart = '/\{startblock\(' . '([^\)]+)' . '\)\}/';
        $arBlocks = self::readBlocks($code, $pregBlockStart, $arBlocks);
        return self::replaceBlocks($code, $arBlocks, true);
    }

    static protected function readBlocks($code, $pregBlockStart, &$arBlocks = array()) {
        if (preg_match_all($pregBlockStart, $code, $arMatches)) {
            $matchOffset = 0;
            for ($blockIndex = 0; $blockIndex < count($arMatches[0]); $blockIndex++) {
                $blockIdent = $arMatches[1][$blockIndex];
                $pregBlock = '/'.preg_quote('{startblock('.$blockIdent.')}').'(.*)'.preg_quote('{endblock('.$blockIdent.')}').'/msU';
                $matchOffset = strpos($code, '{startblock('.$blockIdent.')}', $matchOffset);
                if (preg_match($pregBlock, $code, $arMatchContent, 0, $matchOffset)) {
                    $matchOffset += strlen($arMatchContent[0]);
                    $blockContentParent = (array_key_exists($blockIdent, $arBlocks) ? $arBlocks[$blockIdent] : "");
                    // Add block to array
                    $blockContent = $arMatchContent[1];
                    // Read nested blocks
                    self::readBlocks($blockContent, $pregBlockStart, $arBlocks);
                    $arBlocks[ $blockIdent ] = str_replace('{parentblock()}', $blockContentParent, $blockContent);
                }
            }
        }
        return $arBlocks;
    }

    static protected function replaceBlocks(&$code, &$arBlocks, $finialize = false) {
        do {
            $countReplace = 0;
            foreach ($arBlocks as $blockIdent => $blockContent) {
                $pregBlock = '/'.preg_quote('{startblock('.$blockIdent.')}').'(.*)'.preg_quote('{endblock('.$blockIdent.')}').'/msU';
                // Check if there are already final definitions
                if (preg_match_all('/'.preg_quote('{finalblock('.$blockIdent.')}').'/', $code, $finalMatch)) {
                    $code = preg_replace($pregBlock, '', $code, -1, $count);
                    if ($count > 0) {
                        $countReplace += $count;
                    }
                    $count = count($finalMatch[0]);
                    if ($count <= 1) {
                        $count = 0;
                    }
                } else {
                    // Replace all blocks by the final content
                    $code = preg_replace($pregBlock, '{finalblock('.$blockIdent.')}', $code, -1, $count);
                }
                if ($count > 0) {
                    $pregBlock = '/'.preg_quote('{finalblock('.$blockIdent.')}').'/msU';
                    // Leave only the last block
                    $code = preg_replace($pregBlock, '', $code, $count - 1);
                    $countReplace += $count;
                }
            }
        } while ($countReplace > 0);
        if ($finialize) {
            // Finally remove all blocks from code
            foreach ($arBlocks as $blockIdent => $blockContent) {
                self::removeBlocksFromCode($blockContent);
                $code = preg_replace('/'.preg_quote('{finalblock('.$blockIdent.')}').'/', $blockContent, $code);
                // Replace simple includes
                $code = preg_replace('/'.preg_quote('{block('.$blockIdent.')}').'/', "", $code, -1, $count);
            }
        } else {
            foreach ($arBlocks as $blockIdent => $blockContent) {
                $code = preg_replace('/'.preg_quote('{finalblock('.$blockIdent.')}').'/',
                    '{startblock('.$blockIdent.')}'.$blockContent.'{endblock('.$blockIdent.')}', $code);
            }
        }
        return $code;
    }

    static protected function removeBlocks($filenameBase) {
        $code = file_get_contents($filenameBase);
        if (self::removeBlocksFromCode($code)) {
            file_put_contents($filenameBase, $code);
        }
        return true;
    }

    static protected function removeBlocksFromCode(&$code) {
        $pregBlockStart = '/\{startblock\(' . '([^\)]+)' . '\)\}/';
        if (preg_match_all($pregBlockStart, $code, $arMatches)) {
            for ($blockIndex = 0; $blockIndex < count($arMatches[0]); $blockIndex++) {
                $blockIdent = $arMatches[1][$blockIndex];
                $pregBlock = '/'.preg_quote('{startblock('.$blockIdent.')}').'(.*)'.preg_quote('{endblock('.$blockIdent.')}').'/msU';
                $code = preg_replace($pregBlock, "\\1", $code);
            }
            return true;
        }
        return false;
    }
} 