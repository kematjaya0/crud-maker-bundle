<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use <?= $entity_full_class_name ?>;
use <?= $form_full_class_name ?>;
<?php if (isset($repository_full_class_name)): ?>
use <?= $repository_full_class_name ?>;
<?php endif ?>
<?php if (isset($filter_full_class_name)): ?>
use <?= $filter_full_class_name ?>;
<?php endif ?>
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
<?php if (isset($filter_full_class_name)): ?>
use Kematjaya\BaseControllerBundle\Controller\FilterBuilderController as BaseController;
<?php else:?>
use Kematjaya\BaseControllerBundle\Controller\BasePaginationController as BaseController;
<?php endif ?>

#[Route('<?= $route_path ?>', name:"<?= $route_name ?>_")]
class <?= $class_name ?> extends BaseController<?= "\n" ?>
{
    #[Route(".html", name: "index", methods:["GET", "POST"])]
<?php if (isset($repository_full_class_name)): ?>
    public function index(Request $request, <?= $repository_class_name ?> $<?= $repository_var ?>): Response
    {
<?php if (isset($filter_class_name)): ?>
        $form = $this->createFormFilter(<?= $filter_class_name ?>::class);
        $queryBuilder = $this->buildFilter($request, $form, $<?= $repository_var ?>->createQueryBuilder('t'));
<?php else:?>
        $queryBuilder = $<?= $repository_var ?>->createQueryBuilder('t');
<?php endif ?>

        return $this->render('<?= $templates_path ?>/index.html.twig', [
            '<?= $entity_twig_var_plural ?>' => parent::createPaginator($queryBuilder, $request), <?= "\n" ?>
<?php if (isset($filter_class_name)): ?>
            '<?= $filter_name ?>' => $form->createView()
<?php endif ?>
        ]);
    }
<?php else: ?>
    public function index(Request $request): Response
    {
        $repo = $this->getDoctrine()
            ->getRepository(<?= $entity_class_name ?>::class);
<?php if (isset($filter_class_name)): ?>
        $form = $this->createFormFilter(<?= $filter_class_name ?>::class);
        $queryBuilder = $this->buildFilter($request, $form, $repo->createQueryBuilder('t'));
<?php else:?>
        $queryBuilder = $repo->createQueryBuilder('t');
<?php endif ?>

        return $this->render('<?= $templates_path ?>/index.html.twig', [
            '<?= $entity_twig_var_plural ?>' => parent::createPaginator($queryBuilder, $request), <?= "\n" ?>
<?php if (isset($filter_class_name)): ?>
            '<?= $filter_name ?>' => $form->createView()
<?php endif ?>
        ]);
    }
<?php endif ?>
    #[Route("/create.html", name: "create", methods:["GET", "POST"])]
<?php if ($is_modal):?>
    public function create(Request $request): Response
    {
<?php else:?>
    public function create(Request $request): Response
    {
<?php endif ?>
        $<?= $entity_var_singular ?> = new <?= $entity_class_name ?>();
<?php if ($is_modal):?>
        $form = $this->createForm(<?= $form_class_name ?>::class, $<?= $entity_var_singular ?>, [
            'attr' => ['id' => 'ajaxForm', 'action' => $this->generateUrl('<?= $route_name ?>_create')]
        ]);
        $result = parent::processFormAjax($request, $form);
        if ($result['process']) {
            return $this->json($result);
        }
<?php else:?>
        $form = $this->createForm(<?= $form_class_name ?>::class, $<?= $entity_var_singular ?>);
        $result = parent::processForm($request, $form);
        if ($result) {
            return $this->redirectToRoute('<?= $route_name ?>_index');
        }
<?php endif ?>

        return $this->render('<?= $templates_path ?>/form.html.twig', [
            '<?= $entity_twig_var_singular ?>' => $<?= $entity_var_singular ?>,
            'form' => $form->createView(), 'title' => 'create'
        ]);
    }

    #[Route("/{<?= $entity_identifier ?>}/show.html", name: "show", methods:["GET"])]
<?php if ($is_modal):?>
    public function show(<?= $entity_class_name ?> $<?= $entity_var_singular ?>): Response
    {
<?php else:?>
    public function show(<?= $entity_class_name ?> $<?= $entity_var_singular ?>): Response
    {
<?php endif ?>
        return $this->render('<?= $templates_path ?>/show.html.twig', [
            '<?= $entity_twig_var_singular ?>' => $<?= $entity_var_singular ?>,
        ]);
    }

    #[Route("/{<?= $entity_identifier ?>}/edit.html", name: "edit", methods:["GET","POST"])]
<?php if ($is_modal):?>
    public function edit(Request $request, <?= $entity_class_name ?> $<?= $entity_var_singular ?>): Response
    {
<?php else:?>
    public function edit(Request $request, <?= $entity_class_name ?> $<?= $entity_var_singular ?>): Response
    {
<?php endif ?>
<?php if ($is_modal):?>
        $form = $this->createForm(<?= $form_class_name ?>::class, $<?= $entity_var_singular ?>, [
            'attr' => ['id' => 'ajaxForm', 'action' => $this->generateUrl('<?= $route_name ?>_edit', ['id' => $<?= $entity_var_singular ?>->getId()])]
        ]);
        $result = parent::processFormAjax($request, $form);
        if ($result['process']) {
            return $this->json($result);
        }
<?php else:?>
        $form = $this->createForm(<?= $form_class_name ?>::class, $<?= $entity_var_singular ?>);
        $result = parent::processForm($request, $form);
        if ($result) {
            return $this->redirectToRoute('<?= $route_name ?>_index');
        }
<?php endif ?>
        return $this->render('<?= $templates_path ?>/form.html.twig', [
            '<?= $entity_twig_var_singular ?>' => $<?= $entity_var_singular ?>,
            'form' => $form->createView(), 'title' => 'edit'
        ]);
    }

    #[Route("/{<?= $entity_identifier ?>}/delete.html", name: "delete", methods:["DELETE","POST"])]
    public function delete(Request $request, <?= $entity_class_name ?> $<?= $entity_var_singular ?>): Response
    {
        $tokenName = 'delete'.$<?= $entity_var_singular ?>->get<?= ucfirst($entity_identifier) ?>();
        parent::doDelete($request, $<?= $entity_var_singular ?>, $tokenName);

        return $this->redirectToRoute('<?= $route_name ?>_index');
    }

    #[Route("/bulk-execute.html", name: "bulk_execute", methods:["POST"])]
<?php if (isset($repository_full_class_name)): ?>
    public function bulkExecute(Request $request, <?= $repository_class_name ?> $<?= $repository_var ?>): Response
    {
<?php else: ?>
    public function bulkExecute(Request $request): Response
    {
<?php endif ?>
        if (!$this->isCsrfTokenValid('bulk_execute', $request->request->get('_token'))) {
            $this->addFlash('error', $this->getTranslator()->trans('csrf_token_detected'));

            return $this->redirectToRoute('<?= $route_name ?>_index');
        }

        $ids = $request->request->all('ids');
        $execution = $request->request->get('execution');

        if (empty($ids)) {
            $this->addFlash('error', $this->getTranslator()->trans('no_records_selected'));

            return $this->redirectToRoute('<?= $route_name ?>_index');
        }

<?php if (isset($repository_full_class_name)): ?>
        $<?= $entity_var_plural ?> = $<?= $repository_var ?>->createQueryBuilder('t')
<?php else: ?>
        $repo = $this->getDoctrine()->getRepository(<?= $entity_class_name ?>::class);
        $<?= $entity_var_plural ?> = $repo->createQueryBuilder('t')
<?php endif ?>
            ->where('t.<?= $entity_identifier ?> IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        if ('export' === $execution) {
            return $this->exportCsv($<?= $entity_var_plural ?>);
        }

        if ('delete' === $execution) {
            $manager = $this->getDoctrine()->getManager();
            foreach ($<?= $entity_var_plural ?> as $<?= $entity_var_singular ?>) {
                $manager->remove($<?= $entity_var_singular ?>);
            }
            $manager->flush();

            $this->addFlash('info', $this->getTranslator()->trans('successfull_delete'));
        }

        return $this->redirectToRoute('<?= $route_name ?>_index');
    }

    /**
     * @param <?= $entity_class_name ?>[] $<?= $entity_var_plural ?><?= "\n" ?>
     */
    private function exportCsv(array $<?= $entity_var_plural ?>): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($<?= $entity_var_plural ?>) {
            $handle = fopen('php://output', 'w+');
            // BOM so Excel detects UTF-8 instead of guessing ANSI/Windows-1252.
            fwrite($handle, "\xEF\xBB\xBF");

            // Semicolon: Excel's default CSV list-separator for id-ID and most
            // non-US locales, where comma is already used as the decimal separator.
            fputcsv($handle, [
<?php foreach ($csv_fields as $csvField): ?>
                '<?= addslashes($csvField['label']) ?>',
<?php endforeach; ?>
            ], ';');

            foreach ($<?= $entity_var_plural ?> as $<?= $entity_var_singular ?>) {
                fputcsv($handle, [
<?php foreach ($csv_fields as $csvField): ?>
<?php if ('boolean' === $csvField['kind']): ?>
                    $<?= $entity_var_singular ?>-><?= $csvField['getter'] ?>() ? 'Yes' : 'No',
<?php elseif ('date' === $csvField['kind']): ?>
                    $<?= $entity_var_singular ?>-><?= $csvField['getter'] ?>()?->format('Y-m-d H:i:s'),
<?php elseif ('string' === $csvField['kind']): ?>
                    $this->escapeCsvCell($<?= $entity_var_singular ?>-><?= $csvField['getter'] ?>()),
<?php else: ?>
                    $<?= $entity_var_singular ?>-><?= $csvField['getter'] ?>(),
<?php endif; ?>
<?php endforeach; ?>
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="<?= strtolower($entity_var_plural) ?>.csv"');

        return $response;
    }

    /**
     * Prevent CSV formula injection when the export is opened in spreadsheet software.
     */
    private function escapeCsvCell(?string $value): ?string
    {
        if (null !== $value && 1 === preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
