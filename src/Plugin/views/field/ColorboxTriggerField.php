<?php

namespace Drupal\colorbox_trigger\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Html;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;

/**
 * @ingroup views_field_handlers
 *
 * @ViewsField("colorbox_trigger_field")
 */
class ColorboxTriggerField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We don't need to modify query for this particular example.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['trigger_field'] = array('default' => '');
    $options['popup'] = array('default' => '');
    $options['caption'] = array('default' => '');
    $options['gid'] = array('default' => TRUE);
    $options['custom_gid'] = array('default' => '');
    $options['width'] = array('default' => '600px');
    $options['height'] = array('default' => '400px');

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    // Setup the tokens for fields.
    $previous = $this->getPreviousFieldLabels();
    $optgroup_arguments = (string) t('Arguments');
    $optgroup_fields = (string) t('Fields');
    foreach ($previous as $id => $label) {
      $options[$optgroup_fields]["{{ $id }}"] = substr(strrchr($label, ":"), 2);
    }
    // Add the field to the list of options.
    $options[$optgroup_fields]["{{ {$this->options['id']} }}"] = substr(strrchr($this->adminLabel(), ":"), 2);

    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $options[$optgroup_arguments]["{{ arguments.$arg }}"] = $this->t('@argument title', ['@argument' => $handler->adminLabel()]);
      $options[$optgroup_arguments]["{{ raw_arguments.$arg }}"] = $this->t('@argument input', ['@argument' => $handler->adminLabel()]);
    }

    $this->documentSelfTokens($options[$optgroup_fields]);

    $output = [];
    $output[] = [
      '#markup' => '<p>' . $this->t('You must add some additional fields to this display before using this field. These fields may be marked as <em>Exclude from display</em> if you prefer. Note that due to rendering order, you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.') . '</p>',
    ];

    // We have some options, so make a list.
    if (!empty($options)) {
      foreach (array_keys($options) as $type) {
        if (!empty($options[$type])) {
          $items = [];
          foreach ($options[$type] as $key => $value) {
            $items[] = $key . ' == ' . $value;
          }
          $item_list = [
            '#theme' => 'item_list',
            '#items' => $items,
          ];
          $output[] = $item_list;
        }
      }
    }

    $form['trigger_field'] = array(
      '#type' => 'select',
      '#title' => t('Trigger field'),
      '#description' => t('Select the field that should be turned into the trigger for the Colorbox.  Only fields that appear before this one in the field list may be used.'),
      '#options' => $options['Fields'],
      '#default_value' => $this->options['trigger_field'],
      '#weight' => -12,
    );

    $form['popup'] = array(
      '#type' => 'textarea',
      '#title' => t('Popup'),
      '#description' => t('The Colorbox popup content. You may include HTML. You may enter data from this view as per the "Replacement patterns" below.'),
      '#default_value' => $this->options['popup'],
      '#weight' => -11,
    );

    $form['caption'] = array(
      '#type' => 'textfield',
      '#title' => t('Caption'),
      '#description' => t('The Colorbox Caption. You may include HTML. You may enter data from this view as per the "Replacement patterns" below.'),
      '#default_value' => $this->options['caption'],
      '#weight' => -10,
    );

    $form['gid'] = array(
      '#type' => 'checkbox',
      '#title' => t('Automatic generated Colorbox gallery'),
      '#description' => t('Enable Colorbox gallery using a generated gallery id for this view.'),
      '#default_value' => $this->options['gid'],
      '#weight' => -9,
    );

    $form['custom_gid'] = array(
      '#type' => 'textfield',
      '#title' => t('Custom Colorbox gallery'),
      '#description' => t('Enable Colorbox gallery with a given string as gallery. Overrides the automatically generated gallery id above. You may enter data from this view as per the "Replacement patterns" below.'),
      '#default_value' => $this->options['custom_gid'],
      '#weight' => -8,
    );

    $form['width'] = array(
      '#type' => 'textfield',
      '#title' => t('Width'),
      '#description' => t('Specify the width of the Colorbox popup window. Because the content is dynamic, we cannot detect this value automatically. Example: "100%", 500, "500px".'),
      '#default_value' => $this->options['width'],
      '#weight' => -6,
    );

    $form['height'] = array(
      '#type' => 'textfield',
      '#title' => t('Height'),
      '#description' => t('Specify the height of the Colorbox popup window. Because the content is dynamic, we cannot detect this value automatically. Example: "100%", 500, "500px".'),
      '#default_value' => $this->options['height'],
      '#weight' => -7,
    );

    $form['patterns'] = array(
      '#type' => 'details',
      '#title' => t('Replacement patterns'),
      '#value' => $output,
    );

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {

    // We need to have multiple unique IDs, one for each record.
    static $i = 0;
    $i = mt_rand();

    // Return nothing if no trigger filed is selected.
    if (empty($this->options['trigger_field'])) {
      return;
    }

    // Get the token information and generate the value for the popup and the
    // caption.
    $tokens = $this->getRenderTokens($this->options['alter']);
    $popup = Xss::filterAdmin($this->options['popup']);
    $caption = Xss::filterAdmin($this->options['caption']);
    $gallery = Xss::filterAdmin($this->options['custom_gid']);
    $popup = strtr($popup, $tokens);
    $caption = strtr($caption, $tokens);
    $gallery = Html::getClass(strtr($gallery, $tokens));

    // Return nothing if popup is empty.
    if (empty($popup)) {
      return;
    }

    $width = $this->options['width'] ? $this->options['width'] : '';
    $height = $this->options['height'] ? $this->options['height'] : '';
    $gallery_id = !empty($gallery) ? $gallery : ($this->options['gid'] ? 'gallery-' . $this->view->storage->getOriginalId() : '');
    $link_text = $tokens["{$this->options['trigger_field']}"];
    $link_options = array(
      'fragment' => 'colorbox-inline-' . $i,
      'attributes' => array(
        'class' => array('colorbox-inline-trigger'),
        'data-colorbox-inline' => '#colorbox-inline-' . $i,
        'data-rel' => $gallery_id,
        'data-width' => $width,
        'data-height' => $height,
        'data-title' => $caption,
        'data-inline' => 'true',
      )
    );

    // If the nid is present make the link degrade to the node page if
    // JavaScript is off.
    $link_target = isset($values->nid) ? 'node/' . $values->nid : '';

    $url = Url::fromUri('internal:/' . $link_target, $link_options);
    $link_tag = Link::fromTextAndUrl($link_text, $url)->toRenderable();


    // The outside div is there to hide all of the divs because if the specific Colorbox
    // div is hidden it won't show up as a Colorbox.
    $result = \Drupal::service('renderer')->render($link_tag) . '<div style="display: none;"><div id="colorbox-inline-' . $i . '">' . $popup . '</div></div>';

    return ['#markup' => Markup::create($result)];
  }

}
