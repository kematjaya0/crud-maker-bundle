<?= "<?php\n" ?>

namespace <?= $namespace; ?>;

use <?= $entity_full_class_name ?>; 
use Kematjaya\BaseControllerBundle\FunctionalTest\Controller\AbstractCRUDControllerTest;

class <?= $class_name ?> extends AbstractCRUDControllerTest <?= "\n" ?>
{
    protected function buildObject() 
    {
        $<?= $entity_var_singular ?> = $this->doctrine->getRepository(<?= $entity_class_name ?>::class)->createQueryBuilder('t')
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if (!$<?= $entity_var_singular ?>) {
            $<?= $entity_var_singular ?> = new <?= $entity_class_name ?>();
            // $<?= $entity_var_singular ?>->setName('test'); set data from entity 
        }
        
        return $<?= $entity_var_singular ?>;
    }
    
    public function testIndex()
    {
        <?php if ($include_filter):?>
        parent::doIndex($this->router->generate('<?= $route_name ?>_index'), true);
        <?php else:?>
        parent::doIndex($this->router->generate('<?= $route_name ?>_index'));
        <?php endif ?>
    }
    
    public function testCreate()
    {
        $data = $this->buildObject();
        $post = [
            // set post data 
        ];
        
        $url = $this->generate('<?= $route_name ?>_new');
        <?php if ($is_modal):?>
        parent::ajaxForm($url, $post);
        <?php else: ?>
        parent::processForm($url, $post);
        <?php endif ?>
    }
    
    public function testEdit()
    {
        $data = $this->buildObject();
        $post = [
            // set post data 
        ];
        
        $url = $this->generate('<?= $route_name ?>_edit', ["id" => $data->getId()]);
        <?php if ($is_modal):?>
        parent::ajaxForm($url, $post);
        <?php else: ?>
        parent::processForm($url, $post);
        <?php endif ?>
    }
    
    public function testShow()
    {
        $data = $this->buildObject();
        
        $url = $this->generate('<?= $route_name ?>_show', ["id" => $data->getId()]);
        parent::doShow($url, $data);
    }
    
    public function testDelete()
    {
        $data = $this->buildObject();
        $url = $this->generate('<?= $route_name ?>_delete', ["id" => $data->getId()]);
        parent::doDelete($url, $data);
    }
}
