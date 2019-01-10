<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Sniffs\Annotation;

use PHP_CodeSniffer\Files\File;

/**
 * Class to validate annotation format
 */
class AnnotationFormatValidator
{
    /**
     * Gets the short description end pointer position
     *
     * @param File $phpcsFile
     * @param int $shortPtr
     * @param int $commentEndPtr
     * @return int
     */
    private function getShortDescriptionEndPosition(File $phpcsFile, $shortPtr, $commentEndPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $shortPtrEnd = $shortPtr;
        for ($i = ($shortPtr + 1); $i < $commentEndPtr; $i++) {
            if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                if ($tokens[$i]['line'] === $tokens[$shortPtrEnd]['line'] + 1) {
                    $shortPtrEnd = $i;
                } else {
                    break;
                }
            }
        }
        return $shortPtrEnd;
    }

    /**
     * Validates whether the short description has multi lines in description
     *
     * @param File $phpcsFile
     * @param int $shortPtr
     * @param int $commentEndPtr
     */
    private function validateMultiLinesInShortDescription(
        File $phpcsFile,
        $shortPtr,
        $commentEndPtr
    ) {
        $tokens = $phpcsFile->getTokens();
        $shortPtrEnd = $this->getShortDescriptionEndPosition(
            $phpcsFile,
            (int)$shortPtr,
            $commentEndPtr
        );
        $shortPtrEndContent = $tokens[$shortPtrEnd]['content'];
        if (preg_match('/^[a-z]/', $shortPtrEndContent)
            && $shortPtrEnd != $shortPtr
            && !preg_match('/\bSee\b/', $shortPtrEndContent)
            && $tokens[$shortPtr]['line'] + 1 === $tokens[$shortPtrEnd]['line']
            && $tokens[$shortPtrEnd]['code'] !== T_DOC_COMMENT_TAG
        ) {
            $error = 'Short description should not be in multi lines';
            $phpcsFile->addWarning($error, $shortPtrEnd + 1, 'MethodAnnotation');
        }
    }

    /**
     * Validates whether the spacing between short and long descriptions
     *
     * @param File $phpcsFile
     * @param int $shortPtr
     * @param int $commentEndPtr
     * @param array $emptyTypeTokens
     */
    private function validateSpacingBetweenShortAndLongDescriptions(
        File $phpcsFile,
        $shortPtr,
        $commentEndPtr,
        array $emptyTypeTokens
    ) {
        $tokens = $phpcsFile->getTokens();
        $shortPtrEnd = $this->getShortDescriptionEndPosition(
            $phpcsFile,
            (int)$shortPtr,
            $commentEndPtr
        );
        $shortPtrEndContent = $tokens[$shortPtrEnd]['content'];
        if (preg_match('/^[A-Z]/', $shortPtrEndContent)
            && !preg_match('/\bSee\b/', $shortPtrEndContent)
            && $tokens[$shortPtr]['line'] + 1 === $tokens[$shortPtrEnd]['line']
            && $tokens[$shortPtrEnd]['code'] !== T_DOC_COMMENT_TAG
        ) {
            $error = 'There must be exactly one blank line between lines';
            $phpcsFile->addWarning($error, $shortPtrEnd + 1, 'MethodAnnotation');
        }
        if ($shortPtrEnd != $shortPtr) {
            $this->validateLongDescriptionFormat($phpcsFile, $shortPtrEnd, $commentEndPtr, $emptyTypeTokens);
        } else {
            $this->validateLongDescriptionFormat($phpcsFile, $shortPtr, $commentEndPtr, $emptyTypeTokens);
        }
    }

    /**
     * Validates short description format
     *
     * @param File $phpcsFile
     * @param int $shortPtr
     * @param int $stackPtr
     * @param int $commentEndPtr
     * @param array $emptyTypeTokens
     */
    private function validateShortDescriptionFormat(
        File $phpcsFile,
        $shortPtr,
        $stackPtr,
        $commentEndPtr,
        array $emptyTypeTokens
    ) {
        $tokens = $phpcsFile->getTokens();
        if ($tokens[$shortPtr]['line'] !== $tokens[$stackPtr]['line'] + 1) {
            $error = 'No blank lines are allowed before short description';
            $phpcsFile->addWarning($error, $shortPtr, 'MethodAnnotation');
        }
        if (strtolower($tokens[$shortPtr]['content']) === '{@inheritdoc}') {
            $error = 'If the @inheritdoc not inline it shouldn’t have braces';
            $phpcsFile->addWarning($error, $shortPtr, 'MethodAnnotation');
        }
        $shortPtrContent = $tokens[$shortPtr]['content'];
        if (preg_match('/^\p{Ll}/u', $shortPtrContent) === 1) {
            $error = 'Short description must start with a capital letter';
            $phpcsFile->addWarning($error, $shortPtr, 'MethodAnnotation');
        }
        $this->validateNoExtraNewLineBeforeShortDescription(
            $phpcsFile,
            $stackPtr,
            $commentEndPtr,
            $emptyTypeTokens
        );
        $this->validateSpacingBetweenShortAndLongDescriptions(
            $phpcsFile,
            $shortPtr,
            $commentEndPtr,
            $emptyTypeTokens
        );
        $this->validateMultiLinesInShortDescription(
            $phpcsFile,
            $shortPtr,
            $commentEndPtr
        );
    }

    /**
     * Validates long description format
     *
     * @param File $phpcsFile
     * @param int $shortPtrEnd
     * @param int $commentEndPtr
     * @param array $emptyTypeTokens
     */
    private function validateLongDescriptionFormat(
        File $phpcsFile,
        $shortPtrEnd,
        $commentEndPtr,
        array $emptyTypeTokens
    ) {
        $tokens = $phpcsFile->getTokens();
        $longPtr = $phpcsFile->findNext($emptyTypeTokens, $shortPtrEnd + 1, $commentEndPtr - 1, true);
        if (strtolower($tokens[$longPtr]['content']) === '@inheritdoc') {
            $error = '@inheritdoc imports only short description, annotation must have long description';
            $phpcsFile->addWarning($error, $longPtr, 'MethodAnnotation');
        }
        if ($longPtr !== false && $tokens[$longPtr]['code'] === T_DOC_COMMENT_STRING) {
            if ($tokens[$longPtr]['line'] !== $tokens[$shortPtrEnd]['line'] + 2) {
                $error = 'There must be exactly one blank line between descriptions';
                $phpcsFile->addWarning($error, $longPtr, 'MethodAnnotation');
            }
            if (preg_match('/^\p{Ll}/u', $tokens[$longPtr]['content']) === 1) {
                $error = 'Long description must start with a capital letter';
                $phpcsFile->addWarning($error, $longPtr, 'MethodAnnotation');
            }
        }
    }

    /**
     * Validates tags spacing format
     *
     * @param File $phpcsFile
     * @param int $commentStartPtr
     * @param array $emptyTypeTokens
     */
    public function validateTagsSpacingFormat(File $phpcsFile, $commentStartPtr, array $emptyTypeTokens)
    {
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$commentStartPtr]['comment_tags'][0])) {
            $firstTagPtr = $tokens[$commentStartPtr]['comment_tags'][0];
            $commentTagPtrContent = $tokens[$firstTagPtr]['content'];
            $prevPtr = $phpcsFile->findPrevious($emptyTypeTokens, $firstTagPtr - 1, $commentStartPtr, true);
            if ($tokens[$firstTagPtr]['line'] !== $tokens[$prevPtr]['line'] + 2
                && strtolower($commentTagPtrContent) !== '@inheritdoc'
            ) {
                $error = 'There must be exactly one blank line before tags';
                $phpcsFile->addWarning($error, $firstTagPtr, 'MethodAnnotation');
            }
        }
    }

    /**
     * Validates tag grouping format
     *
     * @param File $phpcsFile
     * @param int $commentStartPtr
     */
    public function validateTagGroupingFormat(File $phpcsFile, $commentStartPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $tagGroups = [];
        $groupId = 0;
        $paramGroupId = null;
        foreach ($tokens[$commentStartPtr]['comment_tags'] as $position => $tag) {
            if ($position > 0) {
                $prevPtr = $phpcsFile->findPrevious(
                    T_DOC_COMMENT_STRING,
                    $tag - 1,
                    $tokens[$commentStartPtr]['comment_tags'][$position - 1]
                );
                if ($prevPtr === false) {
                    $prevPtr = $tokens[$commentStartPtr]['comment_tags'][$position - 1];
                }

                if ($tokens[$prevPtr]['line'] !== $tokens[$tag]['line'] - 1) {
                    $groupId++;
                }
            }

            if (strtolower($tokens[$tag]['content']) === '@param') {
                if ($paramGroupId !== null
                    && $paramGroupId !== $groupId) {
                    $error = 'Parameter tags must be grouped together';
                    $phpcsFile->addWarning($error, $tag, 'MethodAnnotation');
                }
                if ($paramGroupId === null) {
                    $paramGroupId = $groupId;
                }
            }
            $tagGroups[$groupId][] = $tag;
        }
    }

    /**
     * Validates extra newline before short description
     *
     * @param File $phpcsFile
     * @param int $commentStartPtr
     * @param int $commentEndPtr
     * @param array $emptyTypeTokens
     */
    private function validateNoExtraNewLineBeforeShortDescription(
        File $phpcsFile,
        $commentStartPtr,
        $commentEndPtr,
        array $emptyTypeTokens
    ) {
        $tokens = $phpcsFile->getTokens();
        $prevPtr = $phpcsFile->findPrevious($emptyTypeTokens, $commentEndPtr - 1, $commentStartPtr, true);
        if ($tokens[$prevPtr]['line'] < ($tokens[$commentEndPtr]['line'] - 1)) {
            $error = 'Additional blank lines found at end of the annotation block';
            $phpcsFile->addWarning($error, $commentEndPtr, 'MethodAnnotation');
        }
    }

    /**
     * Validates structure description format
     *
     * @param File $phpcsFile
     * @param int $commentStartPtr
     * @param int $shortPtr
     * @param int $commentEndPtr
     * @param array $emptyTypeTokens
     */
    public function validateDescriptionFormatStructure(
        File $phpcsFile,
        $commentStartPtr,
        $shortPtr,
        $commentEndPtr,
        array $emptyTypeTokens
    ) {
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$commentStartPtr]['comment_tags'][0])
        ) {
            $commentTagPtr = $tokens[$commentStartPtr]['comment_tags'][0];
            $commentTagPtrContent = $tokens[$commentTagPtr]['content'];
            if ($tokens[$shortPtr]['code'] !== T_DOC_COMMENT_STRING
                && strtolower($commentTagPtrContent) !== '@inheritdoc'
            ) {
                $error = 'Missing short description';
                $phpcsFile->addWarning($error, $commentStartPtr, 'MethodAnnotation');
            } else {
                $this->validateShortDescriptionFormat(
                    $phpcsFile,
                    (int)$shortPtr,
                    $commentStartPtr,
                    $commentEndPtr,
                    $emptyTypeTokens
                );
            }
        } else {
            $this->validateShortDescriptionFormat(
                $phpcsFile,
                (int)$shortPtr,
                $commentStartPtr,
                $commentEndPtr,
                $emptyTypeTokens
            );
        }
    }
}
