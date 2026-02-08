<?php

namespace App\Controller\Admin;

use App\Entity\Note\Note;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/note')]
class NoteController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    #[Route('/', name: 'app_admin_note_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/note/index.html.twig', [
            'title' => $this->translator->trans('Notes'),
        ]);
    }

    #[Route('/new', name: 'app_admin_note_new', methods: ['GET'])]
    public function new(): Response
    {
        $note = new Note();
        return $this->render('admin/note/edit.html.twig', [
            'title' => $this->translator->trans('Add New Note'),
            'note' => $note,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_note_edit', methods: ['GET'])]
    public function edit(Note $note): Response
    {
        return $this->render('admin/note/edit.html.twig', [
            'title' => $this->translator->trans('Edit Note {title}', ['title' => $note->getTitle()]),
            'note' => $note,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_note_delete', methods: ['POST'])]
    public function delete(Request $request, Note $note, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $note->getId(), $request->request->get('_token'))) {
            $entityManager->remove($note);
            $entityManager->flush();
            $this->addFlash('success', $this->translator->trans('Note deleted successfully.'));
        }

        return $this->redirectToRoute('app_admin_note_index', [], Response::HTTP_SEE_OTHER);
    }
}
