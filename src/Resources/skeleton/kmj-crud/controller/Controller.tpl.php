<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use <?= $entity_full_class_name ?>;
use <?= $form_full_class_name ?>;
use <?= $form_filter_full_class_name ?>;
use Kematjaya\MakerBundle\Controller\Base\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Form;

/**
 * @Route("<?= $route_path ?>")
 */
 class <?= $class_name ?> extends BaseController <?= "\n" ?>
{
    /**
     * @Route("/", name="<?= $route_name ?>_index", methods={"GET", "POST"})
     */
    public function index(Request $request)
    {
        $queryBuilder = $this->getQueryBuilder(<?= $entity_class_name ?>::class);
        
        $filter = <?= $form_filter_class_name ?>::class;
        if($request->get('_reset')) {
            $this->setFilters(null, $filter);
        }
        $form = $this->get('form.factory')->create($filter, $this->getFilters($filter));
        $filters = $request->get($form->getName());
        if ($filters) {
            $form->submit($filters);
            $this->setFilters($filters, $filter);
        }
        $this->getFilterAdapter()->addFilterConditions($form, $queryBuilder);
        
        $paginator = $this->createPaginator($request, $queryBuilder->getQuery());
        return $this->render('<?= $templates_path ?>/index.html.twig', array(
            'title' => $this->getTranslator()->trans('<?= $route_name ?>'),
            'pagers' => $paginator,
            'filter' => $form->createView()
        ));
        
    }
    
    /**
     * @Route("/create", name="<?= $route_name ?>_create", methods={"GET","POST"})
     */
    public function create(Request $request)
    {
        $<?= $entity_var_singular ?> = new <?= $entity_class_name ?>();
        $form = $this->createForm(<?= $form_class_name ?>::class, $<?= $entity_var_singular ?>);
        
        if($this->processForm($form, $request)) {
            return $this->redirectToRoute('<?= $route_name ?>_index');
        }
        return $this->render('<?= $templates_path ?>/create.html.twig', array(
            'title' => $this->getTranslator()->trans('<?= $route_name ?>_create'),
            'form' => $form->createView()
        ));
        
    }
    
    /**
     * @Route("/edit/{<?= $entity_identifier ?>}", name="<?= $route_name ?>_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, $<?= $entity_identifier ?>)
    {
        $<?= $entity_var_singular ?> = $this->getById($<?= $entity_identifier ?>);
        if(!$<?= $entity_var_singular ?>) {
            $this->addFlash('error', $this->getTranslator()->trans('object.not_found'));
            return $this->redirectToRoute('<?= $route_name ?>_index');
        }
        
        $form = $this->createForm(<?= $form_class_name ?>::class, $<?= $entity_var_singular ?>);
        
        if ($this->processForm($form, $request)) {
            return $this->redirectToRoute('<?= $route_name ?>_index');
        }
        
        return $this->render('<?= $templates_path ?>/edit.html.twig', [
            'title' => $this->getTranslator()->trans('<?= $route_name ?>_edit'),
            '<?= $entity_twig_var_singular ?>' => $<?= $entity_var_singular ?>,
            'form' => $form->createView()
        ]);
        
    }
    
    private function processForm(Form $form, Request $request)
    {
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            $type = ($form->getData()->getId()) ? "update": "add";
            if($form->isValid()) {
                
                $em = $this->getDoctrine()->getManager();
                $con = $em->getConnection();
                try{
                    $con->beginTransaction();
                    $em->persist($form->getData());
                    $em->flush();
                    $con->commit();
                    $this->addFlash('success', $this->getTranslator()->trans('messages.'.$type.'.success'));
                    return true;
                } catch (Exception $ex) {
                    $this->addFlash('error', $this->getTranslator()->trans('messages.'.$type.'.error') . ' : ' . $ex->getMessages());
                    $con->rollBack();
                    return false;
                }
            }else{
                $this->addFlash('error', $this->getTranslator()->trans('messages.'.$type.'.error'));
                return false;
            }
        }
        
        return false;
    }
    
    
    /**
     * @Route("/show/{<?= $entity_identifier ?>}", name="<?= $route_name ?>_show", methods={"GET"})
     */
    public function show(Request $request, $<?= $entity_identifier ?>)
    {
        $<?= $entity_var_singular ?> = $this->getById($<?= $entity_identifier ?>);
        if(!$<?= $entity_var_singular ?>) {
            $this->addFlash('error', $this->getTranslator()->trans('object.not_found'));
            return $this->redirectToRoute('<?= $route_name ?>_index');
        }
        
        return $this->render('<?= $templates_path ?>/show.html.twig', [
            '<?= $entity_twig_var_singular ?>' => $<?= $entity_var_singular ?>,
        ]);
    }
    
    
    /**
     * @Route("/delete/{<?= $entity_identifier ?>}", name="<?= $route_name ?>_delete", methods={"POST","DELETE"})
     */
    public function delete(Request $request, $<?= $entity_identifier ?>)
    {
        $<?= $entity_var_singular ?> = $this->getById($<?= $entity_identifier ?>);
        if(!$<?= $entity_var_singular ?>) {
            $this->addFlash('error', $this->getTranslator()->trans('object.not_found'));
            return $this->redirectToRoute('<?= $route_name ?>_index');
        }
        if ($this->isCsrfTokenValid('delete'.$<?= $entity_var_singular ?>->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $con = $em->getConnection();
            try{
                $con->beginTransaction();
                $em->remove($<?= $entity_var_singular ?>);
                $em->flush();
                $con->commit();
                $this->addFlash('success', $this->getTranslator()->trans('messages.deleted.success'));
                return $this->redirectToRoute('<?= $route_name ?>_index');
            } catch (Exception $ex) {
                $this->addFlash('error', $this->getTranslator()->trans('messages.deleted.error') . ' : ' . $ex->getMessages());
                $con->rollBack();
            }
        }else{
            $this->addFlash('error', $this->getTranslator()->trans('messages.deleted.error'));
        }

        return $this->redirectToRoute('<?= $route_name ?>_index');
    }
    
    private function getById($<?= $entity_identifier ?>)
    {
        return $this->getDoctrine()->getRepository(<?= $entity_class_name ?>::class)->find($<?= $entity_identifier ?>);
    }
}