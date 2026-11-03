<?php
namespace App\Twig; use App\Catalog\CatalogMetrics; use Twig\Extension\AbstractExtension; use Twig\TwigFunction;
final class CatalogMetricsExtension extends AbstractExtension { public function __construct(private CatalogMetrics $metrics){} public function getFunctions():array{return [new TwigFunction('catalog_metrics',fn()=> $this->metrics->totals())];} }
