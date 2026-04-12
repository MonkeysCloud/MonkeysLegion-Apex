<?php



/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

declare(strict_types=1);

namespace MonkeysLegion\Apex\Embedding;

/**
 * Similarity computation utilities.
 */
final class Similarity
{
    /**
     * Cosine similarity between two equal-length vectors.
     *
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function cosine(array $a, array $b): float
    {
        if (count($a) !== count($b) || empty($a)) {
            return 0.0;
        }

        $dot    = 0.0;
        $normA  = 0.0;
        $normB  = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0 ? $dot / $denom : 0.0;
    }

    /**
     * Euclidean distance between two vectors.
     *
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function euclidean(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0.0;
        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    /**
     * Dot product of two vectors.
     *
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function dotProduct(array $a, array $b): float
    {
        $sum = 0.0;
        for ($i = 0, $len = min(count($a), count($b)); $i < $len; $i++) {
            $sum += $a[$i] * $b[$i];
        }
        return $sum;
    }
}
