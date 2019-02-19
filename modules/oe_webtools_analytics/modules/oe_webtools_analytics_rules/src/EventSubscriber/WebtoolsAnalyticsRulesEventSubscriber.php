<?php

declare(strict_types = 1);

namespace Drupal\oe_webtools_analytics_rules\EventSubscriber;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_webtools_analytics\Event\AnalyticsEvent;
use Drupal\oe_webtools_analytics\AnalyticsEventInterface;
use Drupal\oe_webtools_analytics_rules\RuleMatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for the Webtools Analytics event.
 */
class WebtoolsAnalyticsRulesEventSubscriber implements EventSubscriberInterface {

  /**
   * The rule matcher.
   *
   * @var \Drupal\oe_webtools_analytics_rules\RuleMatcherInterface
   */
  protected $ruleMatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * WebtoolsAnalyticsEventSubscriber constructor.
   *
   * @param \Drupal\oe_webtools_analytics_rules\RuleMatcherInterface $ruleMatcher
   *   The rule matcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(RuleMatcherInterface $ruleMatcher, EntityTypeManagerInterface $entityTypeManager) {
    $this->ruleMatcher = $ruleMatcher;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Event listener that returns the rule that matches the current path.
   *
   * When an AnalyticsEvent is fired this will match the current path against
   * all Webtools Analytics rules that are defined and set the resulting site
   * section on the event if a match is found.
   *
   * @param \Drupal\oe_webtools_analytics\AnalyticsEventInterface $event
   *   The analytics event.
   */
  public function onAnalyticsEvent(AnalyticsEventInterface $event): void {
    // We return results based on rule entities. This means that if a rule is
    // added or deleted, or if any of the existing rules change, the cached
    // results should be invalidated.
    $webtools_analytics_rule_definition = $this->getWebtoolsAnalyticsRuleDefinition();
    $webtools_rules_cache_tags = $webtools_analytics_rule_definition->getListCacheTags();
    $event->addCacheTags($webtools_rules_cache_tags);
    $event->addCacheContexts($webtools_analytics_rule_definition->getListCacheContexts());

    // Since the rules that are used to discover the site sections are URI based
    // the result cache should vary based on the path.
    $event->addCacheContexts(['url.path']);

    // Store the section that matches the current path on the event if it is'
    // found.
    $section = $this->ruleMatcher->getMatchingSection();
    if ($section) {
      $event->setSiteSection($section);
    }
  }

  /**
   * Returns the entity type definition for the Webtools Analytics Rule entity.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type definition.
   */
  protected function getWebtoolsAnalyticsRuleDefinition(): EntityTypeInterface {
    try {
      $definition = $this->entityTypeManager->getDefinition('webtools_analytics_rule');
    }
    catch (PluginNotFoundException $e) {
      // The entity type manager in core will throw a checked exception if an
      // entity type is not defined. This is intended to deal with situations
      // like the module that defines the entity type not being enabled. In our
      // case we are sure that the entity type exists since we define it in our
      // own module. We can convert this to an unchecked exception so this
      // doesn't need to be checked again higher in the call stack.
      throw new \RuntimeException('The webtools_analytics_rule entity type does not exist.', 0, $e);
    }

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AnalyticsEvent::NAME][] = ['onAnalyticsEvent'];

    return $events;
  }

}
