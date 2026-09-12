<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * docs/02-diseno.md §7. Incluye también las abilities de la foto de
 * enrolamiento (llamadas "FacePhotoPolicy" en el diseño) — se consolidan
 * aquí porque en Laravel una Policy ya está atada 1:1 a un modelo (Student),
 * y separarlas en una clase aparte solo añadiría indirección sin cambiar
 * el comportamiento.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Student $student): bool
    {
        return $user->isAdmin() || $this->isSelf($user, $student);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    /**
     * Subir la foto de enrolamiento inicial: el propio estudiante (una sola
     * vez; el "ya existe" se valida como conflicto de negocio, no aquí) o el
     * admin (por si el estudiante aún no tiene cuenta). Ver docs/02-diseno.md §1.
     */
    public function uploadFacePhoto(User $user, Student $student): bool
    {
        return $user->isAdmin() || $this->isSelf($user, $student);
    }

    /**
     * Reemplazar un enrolamiento existente: exclusivo del admin.
     */
    public function replaceFacePhoto(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    private function isSelf(User $user, Student $student): bool
    {
        return $user->student?->id === $student->id;
    }
}
