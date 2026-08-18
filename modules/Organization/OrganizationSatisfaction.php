<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;

final readonly class OrganizationSatisfaction
{
    public function __construct(private PDO $database) {}

    public function submit(int $organizationId, int $customerId, int $rating, string $comment, ?int $enrollmentId = null): void
    {
        if ($rating < 1 || $rating > 5) throw new RuntimeException('Avaliação inválida.');
        $comment = trim($comment);
        if (mb_strlen($comment) > 1000) throw new RuntimeException('O comentário deve ter no máximo 1.000 caracteres.');
        if ($enrollmentId !== null) {
            $existing = $this->database->prepare('SELECT id FROM portal_satisfaction_responses WHERE finance_customer_id=:customer AND enrollment_id=:enrollment LIMIT 1');
            $existing->execute(['customer' => $customerId, 'enrollment' => $enrollmentId]);
            $id = $existing->fetchColumn();
            if ($id !== false) {
                $s = $this->database->prepare('UPDATE portal_satisfaction_responses SET rating=:rating,comment=:comment,created_at=NOW() WHERE id=:id');
                $s->execute(['rating' => $rating, 'comment' => $comment === '' ? null : $comment, 'id' => (int) $id]);
                return;
            }
        }
        $s = $this->database->prepare('INSERT INTO portal_satisfaction_responses(organization_id,finance_customer_id,enrollment_id,rating,comment) VALUES(:org,:customer,:enrollment,:rating,:comment)');
        $s->execute(['org' => $organizationId, 'customer' => $customerId, 'enrollment' => $enrollmentId, 'rating' => $rating, 'comment' => $comment === '' ? null : $comment]);
    }

    public function ratedRecently(int $customerId, int $days = 90): bool
    {
        $s = $this->database->prepare('SELECT COUNT(*) FROM portal_satisfaction_responses WHERE finance_customer_id=:customer AND created_at>=NOW()-INTERVAL ' . max(1, min(365, $days)) . ' DAY');
        $s->execute(['customer' => $customerId]);
        return (int) $s->fetchColumn() > 0;
    }

    public function ratedForEnrollment(int $customerId, int $enrollmentId, int $days = 90): bool
    {
        $s = $this->database->prepare('SELECT COUNT(*) FROM portal_satisfaction_responses WHERE finance_customer_id=:customer AND enrollment_id=:enrollment AND created_at>=NOW()-INTERVAL ' . max(1, min(365, $days)) . ' DAY');
        $s->execute(['customer' => $customerId, 'enrollment' => $enrollmentId]);
        return (int) $s->fetchColumn() > 0;
    }

    /** @return list<int> */
    public function ratedEnrollmentIds(int $customerId, int $days = 90): array
    {
        $s = $this->database->prepare('SELECT DISTINCT enrollment_id FROM portal_satisfaction_responses WHERE finance_customer_id=:customer AND enrollment_id IS NOT NULL AND created_at>=NOW()-INTERVAL ' . max(1, min(365, $days)) . ' DAY');
        $s->execute(['customer' => $customerId]);
        $ids = [];
        foreach ($s->fetchAll() as $row) {
            $ids[] = (int) $row['enrollment_id'];
        }
        return $ids;
    }

    /** @return array{avg:float,count:int,stars:array<int,int>} */
    public function summary(int $organizationId): array
    {
        $row = $this->database->query("SELECT COUNT(*) c,COALESCE(AVG(rating),0) avg_rating FROM portal_satisfaction_responses WHERE organization_id=" . (int) $organizationId)->fetch() ?: [];
        $stars = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $dist = $this->database->prepare('SELECT rating,COUNT(*) c FROM portal_satisfaction_responses WHERE organization_id=:org GROUP BY rating');
        $dist->execute(['org' => $organizationId]);
        foreach ($dist->fetchAll() as $rowDist) {
            $stars[(int) $rowDist['rating']] = (int) $rowDist['c'];
        }
        return ['avg' => round((float) ($row['avg_rating'] ?? 0), 1), 'count' => (int) ($row['c'] ?? 0), 'stars' => $stars];
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $organizationId, int $limit = 50): array
    {
        $s = $this->database->prepare('SELECT r.rating,r.comment,r.created_at,f.name student_name,mc.fullname course_name FROM portal_satisfaction_responses r INNER JOIN finance_customers f ON f.id=r.finance_customer_id LEFT JOIN student_enrollments e ON e.id=r.enrollment_id LEFT JOIN moodle_courses mc ON mc.id=e.moodle_course_id WHERE r.organization_id=:org ORDER BY r.created_at DESC,r.id DESC LIMIT ' . max(1, min(200, $limit)));
        $s->execute(['org' => $organizationId]);
        return $s->fetchAll() ?: [];
    }
}
