<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_content_persistent\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the PersistentUrlController response.
 *
 * @group oe_content
 */
class PersistentUrlControllerTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'path',
    'node',
    'user',
    'system',
    'dynamic_page_cache',
    'page_cache',
    'oe_content_persistent',
    'oe_content_persistent_test',
    'path_alias',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    NodeType::create(['type' => 'page'])->save();
    NodeType::create(['type' => 'article'])->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
  }

  /**
   * Tests the response of the Persistent Controller.
   */
  public function testPersistentUrlController(): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'title' => 'Test node title',
      'type' => 'page',
      'path' => ['alias' => '/foo'],
      'status' => TRUE,
      'uid' => 0,
    ]);
    $translation = $node->addTranslation('fr', $node->toArray());
    $translation->setTitle('Titre test');
    $node->save();

    $this->drupalGet('/content/' . $node->uuid());
    $this->assertResponse(200);
    $this->assertUrl('/foo');
    $this->assertSession()->responseContains('Test node title');

    // Try the node translation.
    $this->drupalGet('/fr/content/' . $node->uuid());
    $this->assertResponse(200);
    $this->assertUrl('/fr/foo');
    $this->assertSession()->responseContains('Titre test');
    $this->assertSession()->responseNotContains('Test node title');

    $node = \Drupal::service('entity_type.manager')->getStorage('node')->load($node->id());
    $node->path->alias = '/foo2';
    $node->save();

    // Ensure the cache is invalidated correctly.
    $this->drupalGet('/content/' . $node->uuid());
    $this->assertResponse(200);
    $this->assertUrl('/foo2');
    $this->assertSession()->responseContains('Test node title');

    $node->path->alias = '';
    $node->save();

    $this->drupalGet('/content/' . $node->uuid());
    $this->assertResponse(200);
    $this->assertUrl('/node/' . $node->id());
    $this->assertSession()->responseContains('Test node title');

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    $node = \Drupal::service('entity_type.manager')->getStorage('node')->load($node->id());

    // Try an external redirect.
    $node->setTitle('External');
    $node->save();
    $this->drupalGet('/content/' . $node->uuid());
    $this->assertResponse(200);
    $this->assertUrl('https://ec.europa.eu');

    // Check try to get not existing entity.
    $this->drupalGet('/content/' . \Drupal::service('uuid')->generate());
    $this->assertResponse(404);

    // Not valid uuid.
    $this->drupalGet('/content/' . $this->randomMachineName());
    $this->assertResponse(404);

    // Create a new node that will be sent to the home page by the controller.
    $node = Node::create([
      'title' => 'Test node title',
      'type' => 'article',
      'path' => ['alias' => '/bar'],
      'status' => TRUE,
      'uid' => 0,
    ]);
    $node->save();

    $this->drupalGet('/content/' . $node->uuid());
    $this->assertResponse(200);
    // We should be redirected to the home page.
    $this->assertUrl('/');
    $this->assertSession()->responseNotContains('Test node title');
  }

}
