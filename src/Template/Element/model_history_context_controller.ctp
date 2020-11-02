<?php
use Cake\Routing\Router;

$url = Router::url([
    'controller' => $historyEntry->context['params']['controller'],
    'action' => $historyEntry->context['params']['action'],
    'plugin' => $historyEntry->context['params']['plugin']
] + $historyEntry->context['params']['pass']);

?>

<a href="<?= $url ?>" title="<?= $url ?>"><?= __d('model_history', 'called_site') ?></a>
