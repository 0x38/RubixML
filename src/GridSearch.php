<?php

namespace Rubix\ML;

use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Clusterers\Clusterer;
use Rubix\ML\Regressors\Regressor;
use Rubix\ML\Classifiers\Classifier;
use Rubix\ML\CrossValidation\Validator;
use InvalidArgumentException;
use ReflectionClass;

class GridSearch implements Classifier, Clusterer, Regressor, Persistable
{
    /**
     * The reflector instance of the base estimator.
     *
     * @var \ReflectionClass
     */
    protected $reflector;

    /**
     * The grid of hyperparameters i.e. constructor arguments of the base
     * estimator.
     *
     * @var array
     */
    protected $params = [
        //
    ];

    /**
     * The validator used to score each trained estimator.
     *
     * @var \Rubix\ML\CrossValidation\Validator
     */
    protected $validator;

    /**
     * The argument names for the base estimator's constructor.
     *
     * @var array
     */
    protected $args = [
        //
    ];

    /**
     * The results of a grid search.
     *
     * @var array
     */
    protected $results = [
        //
    ];

    /**
     * @param  string  $base
     * @param  array  $params
     * @param  \Rubix\ML\CrossValidation\Validator  $validator
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __construct(string $base, array $params, Validator $validator)
    {
        $reflector = new ReflectionClass($base);

        if (!in_array(Estimator::class, $reflector->getInterfaceNames())) {
            throw new InvalidArgumentException('Base class must implement the'
                . ' estimator inteferace.');
        }

        $args = array_column($reflector->getConstructor()->getParameters(),
            'name');

        if (count($params) > count($args)) {
            throw new InvalidArgumentException('Too many arguments supplied.'
                . count($params) . ' given, only ' . count($args) . ' needed.');
        }

        foreach ($params as &$params) {
            $params = (array) $params;
        }

        $this->reflector = $reflector;
        $this->args = array_slice($args, 0, count($params));
        $this->params = $params;
        $this->validator = $validator;
    }

    /**
     * The results of the last grid search.
     *
     * @return array
     */
    public function results() : array
    {
        return $this->results;
    }

    /**
     * Train one estimator per combination of parameters given by the grid and
     * assign the best one as the base estimator of this instance.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @throws \InvalidArgumentException
     * @return void
     */
    public function train(Dataset $dataset) : void
    {
        if (!$dataset instanceof Labeled) {
            throw new InvalidArgumentException('This Estimator requires a'
                . ' Labeled training set.');
        }

        $best = ['score' => -INF, 'estimator' => null];

        $this->results = [];

        foreach ($this->combineParams($this->params) as $params) {
            $estimator = $this->reflector->newInstanceArgs($params);

            $score = $this->validator->score($estimator, $dataset);

            if ($score > $best['score']) {
                $best['score'] = $score;
                $best['estimator'] = $estimator;
            }

            $this->results[] = [
                'score' => $score,
                'params' => array_combine($this->args, $params),
            ];
        }

        $this->estimator = $best['estimator'];
    }

    /**
     * Make a prediction on a given sample dataset.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $samples
     * @return array
     */
    public function predict(Dataset $dataset) : array
    {
        return $this->estimator->predict($samples);
    }

    /**
     * Return an array of all possible combinations of parameters. i.e. the
     * Cartesian product of the supplied parameter grid.
     *
     * @param  array  $params
     * @return array
     */
    protected function combineParams(array $params) : array
    {
        $params = [[]];

        foreach ($params as $i => $options) {
            $append = [];

            foreach ($params as $product) {
                foreach ($options as $option) {
                    $product[$i] = $option;
                    $append[] = $product;
                }
            }

            $params = $append;
        }

        return $params;
    }

    /**
     * Allow methods to be called on the estimator from the wrapper.
     *
     * @param  string  $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->estimator->$name(...$arguments);
    }
}
