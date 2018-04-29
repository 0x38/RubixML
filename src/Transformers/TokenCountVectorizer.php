<?php

namespace Rubix\Engine\Transformers;

use Rubix\Engine\Datasets\Dataset;
use Rubix\Engine\Transformers\Tokenizers\Tokenizer;
use Rubix\Engine\Transformers\Tokenizers\WhitespaceTokenizer;
use InvalidArgumentException;

class TokenCountVectorizer implements Transformer
{
    /**
     * The maximum size of the vocabulary.
     *
     * @var int
     */
    protected $maxVocabulary;

    /**
     * The tokenizer used to extract text data into tokenable values.
     *
     * @var \Rubix\Engine\Transformers\Tokenizers\Tokenizer
     */
    protected $tokenizer;

    /**
     * The column types of the fitted dataset. i.e. categorical or continuous.
     *
     * @var array
     */
    protected $columnTypes = [
        //
    ];

    /**
     * The vocabulary of the fitted training set.
     *
     * @var array
     */
    protected $vocabulary = [
        //
    ];

    /**
     * @param  int  $maxVocabulary
     * @param  \Rubix\Engine\Transformers\Tokenizers\Tokenizer  $tokenizer
     * @return void
     */
    public function __construct(int $maxVocabulary = PHP_INT_MAX, Tokenizer $tokenizer = null)
    {
        if ($maxVocabulary < 1) {
            throw new InvalidArgumentException('Max vocabulary must be greater than 0.');
        }

        if (!isset($tokenizer)) {
            $tokenizer = new WhitespaceTokenizer();
        }

        $this->maxVocabulary = $maxVocabulary;
        $this->tokenizer = $tokenizer;
    }

    /**
     * @return array
     */
    public function vocabulary() : array
    {
        return array_flip($this->vocabulary);
    }

    /**
     * @return int
     */
    public function vocabularySize() : int
    {
        return count($this->vocabulary);
    }

    /**
     * Build the vocabulary for the vectorizer.
     *
     * @param  \Rubix\Engine\Datasets\Dataset  $dataset
     * @return void
     */
    public function fit(Dataset $dataset) : void
    {
        $this->columnTypes = $dataset->columnTypes();
        $this->vocabulary = [];
        $counts = [];

        foreach ($dataset->samples() as $sample) {
            foreach ($sample as $column => $feature) {
                if ($this->columnTypes[$column] === self::CATEGORICAL) {
                    foreach ($this->tokenizer->tokenize($feature) as $token) {
                        if (isset($counts[$token])) {
                            $counts[$token]++;
                        } else {
                            $counts[$token] = 1;
                        }
                    }
                }
            }
        }

        if (count($counts) > $this->maxVocabulary) {
            arsort($counts);

            $counts = array_splice($counts, 0, $this->maxVocabulary);
        }

        foreach ($counts as $token => $count) {
            $this->vocabulary[$token] = count($this->vocabulary);
        }
    }

    /**
     * Transform the text dataset into a collection of vectors where the value
     * is equal to the number of times that word appears in the sample.
     *
     * @param  array  $samples
     * @return void
     */
    public function transform(array &$samples) : void
    {
        foreach ($samples as &$sample) {
            foreach ($this->columnTypes as $column => $type) {
                $vectors = [];

                if ($type === self::CATEGORICAL) {
                    $vectors[] = $this->vectorize($sample[$column]);
                }

                unset($sample[$column]);
            }

            $sample = array_merge(array_values($sample), ...$vectors);
        }
    }

    /**
     * Convert a string into a vector where the scalars are token counts.
     *
     * @param  string  $sample
     * @return array
     */
    public function vectorize(string $string) : array
    {
        $vector = array_fill_keys($this->vocabulary, 0);

        foreach ($this->tokenizer->tokenize($string) as $token) {
            if (isset($this->vocabulary[$token])) {
                $vector[$this->vocabulary[$token]] += 1;
            }
        }

        return $vector;
    }
}