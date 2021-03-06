<?php

namespace AFieldGuideToElephpants\JsonBundle;

use Dflydev\DotAccessConfiguration\Configuration;
use Sculpin\Core\Event\SourceSetEvent;
use Sculpin\Core\Source\AbstractSource;
use Sculpin\Core\Source\SourceInterface;

class JsonBuilder extends AbstractSource
{
    private $config;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public function onSculpinCoreAfterFormat(SourceSetEvent $event)
    {
        // Generate elephpant data
        $elephpants = [];
        foreach ($event->sourceSet()->allSources() as $source) {
            if ($this->isElephpant($source)) {
                $key = rtrim($source->filename(), '.md');
                $elephpants[$key] = $this->elephpantToArray($source);
            }
        }

        // Create new JSON file with the elephpant data
        $json = new DynamicJsonSource('data/all.json');
        $json->setContent($elephpants);
        $event->sourceSet()->mergeSource($json);
    }

    private function isElephpant(SourceInterface $source)
    {
        return in_array($source->data()->get('layout'), array('elephpant', 'other'));
    }

    private function elephpantToArray(SourceInterface $source)
    {
        $data = $source->data()->export();

        list($variation, $species) = $this->getVariationAndSpecies($data['categories'][0] ?? false);

        $photos = $this->expandPhotosData($data['photos'] ?? []);

        return array_filter(array(
            'name' => $data['name'] ?? '',
            'variation' => $variation,
            'species' => $species,
            'color' => $data['tags'][0] ?? '',
            'sponsor' => $data['sponsor'] ?? '',
            'reverse' => $data['reverse'] ?? '',
            'photos' => $photos ?? '',
            'description' => $source->content(),
        ));
    }

    /**
     * Obtains the common and Latin names using the given category
     *
     * @param string $category
     *
     * @return string[] A poor-mans tuple
     */
    private function getVariationAndSpecies($category)
    {
        $species = $this->config->get('latinname');
        $variation = null;

        if ($subspecies = $this->config->get('subspecies.'.$category)) {
            $species .= ' ' . $subspecies['latin'];
            $variation = $subspecies['common'];
        } elseif ($subspecies = $this->config->get('relatedspecies.'.$category)) {
            $species = $subspecies['latin'];
            $variation = $subspecies['common'];
        }

        return [$variation, $species];
    }

    /**
     * Expand photo data to include complete paths
     *
     * @param array $photos
     *
     * @return array
     */
    private function expandPhotosData(array $photos) {
        $protocol = $this->config->get('https') ? 'https' : 'http';
        foreach ($photos as &$photo) {
            if ($photo['file']) {
                $photo['file'] = $protocol . '://' . $this->config->get('hostname') . '/photos/' . $photo['file'];
            }
        }

        return $photos;
    }
}
