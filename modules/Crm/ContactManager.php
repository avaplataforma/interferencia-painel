<?php

declare(strict_types=1);

namespace Interferencia\Modules\Crm;

use DateTimeImmutable;
use RuntimeException;

final readonly class ContactManager
{
    public function __construct(private ContactRepository $contacts, private TagRepository $tags) {}

    /** @param array<string, mixed> $data */
    public function save(?int $id, int $unitId, int $creatorId, array $data): int
    {
        $before = $id === null ? null : $this->contacts->find($id, $unitId);
        if ($id !== null && $before === null) throw new RuntimeException('Contato não encontrado nesta unidade.');
        $beforeTagIds = $id === null ? [] : $this->tags->idsForContact($id);
        $statusId = (int) ($data['status_id'] ?? 0);
        if (!$this->contacts->statusExists($statusId)) throw new RuntimeException('Selecione um status válido.');
        $responsible = (int) ($data['responsible_user_id'] ?? 0);
        if ($responsible <= 0) throw new RuntimeException('Selecione um atendente.');
        if ($responsible > 0 && !$this->contacts->userBelongsToUnit($responsible, $unitId)) throw new RuntimeException('Atendente indisponível nesta unidade.');
        $score = ($data['interest_score'] ?? '') === '' ? null : (int) $data['interest_score'];
        if ($score === null) throw new RuntimeException('Informe o interesse do contato.');
        if ($score !== null && ($score < 0 || $score > 10)) throw new RuntimeException('O interesse deve estar entre 0 e 10.');
        $source = (string) ($data['registration_source'] ?? 'internal');
        if (!in_array($source, ['internal', 'external_form'], true)) throw new RuntimeException('Origem de cadastro inválida.');
        $registered = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) ($data['registered_at'] ?? ''));
        if ($registered === false) throw new RuntimeException('Informe uma data e hora válidas.');

        $clean = ['status_id'=>$statusId, 'responsible_user_id'=>$responsible ?: null, 'name'=>trim((string)$data['name'],), 'phone'=>$this->nullable($data['phone'] ?? null), 'email'=>$this->nullable($data['email'] ?? null), 'document'=>$this->nullable($data['document'] ?? null), 'course'=>$this->nullable($data['course'] ?? null), 'interest_score'=>$score, 'origin_city'=>$this->nullable($data['origin_city'] ?? null), 'registration_source'=>$source, 'registered_at'=>$registered->format('Y-m-d H:i:s'), 'notes'=>$this->nullable($data['notes'] ?? null), 'is_active'=>(int)(($data['is_active'] ?? null)==='1')];
        $phoneDigits=preg_replace('/\D/','',(string)($data['phone']??''));if(!in_array(strlen((string)$phoneDigits),[10,11],true))throw new RuntimeException('Informe um telefone com DDD válido.');
        $documentDigits=preg_replace('/\D/','',(string)($data['document']??''));if($documentDigits!==''&&!$this->validDocument((string)$documentDigits))throw new RuntimeException('Informe um CPF ou CNPJ válido.');
        if($documentDigits!==''){$conflict=$this->contacts->documentConflict($documentDigits,$id);if($conflict!==null)throw new RuntimeException($conflict['type']==='student'?'Este CPF já pertence ao aluno '.$conflict['name'].'.':'Este CPF já pertence ao lead '.$conflict['name'].'.');}
        $tagIds=array_values(array_unique(array_map('intval',is_array($data['tags']??null)?$data['tags']:[])));if($tagIds===[])throw new RuntimeException('Selecione pelo menos uma etiqueta.');if(!$this->tags->validIds($tagIds))throw new RuntimeException('Uma ou mais etiquetas são inválidas.');
        $saved=$this->contacts->save($id, $unitId, $creatorId, $clean);
        $this->tags->syncContact($saved,$tagIds);
        if ($before === null) {
            $this->contacts->recordEvent($saved,$creatorId,'created','Contato cadastrado internamente.');
        } else {
            if ((int)$before['status_id'] !== $statusId) $this->contacts->recordEvent($saved,$creatorId,'status_changed','Status alterado de '.$this->contacts->statusName((int)$before['status_id']).' para '.$this->contacts->statusName($statusId).'.');
            if ((int)$before['responsible_user_id'] !== $responsible) $this->contacts->recordEvent($saved,$creatorId,'responsible_changed','Atendente alterado de '.$this->contacts->userName((int)$before['responsible_user_id']).' para '.$this->contacts->userName($responsible).'.');
            $oldTags=$beforeTagIds;$newTags=$tagIds;sort($oldTags);sort($newTags);if($oldTags!==$newTags)$this->contacts->recordEvent($saved,$creatorId,'tags_changed','Etiquetas atualizadas: '.implode(', ',$this->tags->namesForIds($tagIds)).'.');
            if ((string)$before['notes'] !== (string)$clean['notes']) $this->contacts->recordEvent($saved,$creatorId,'notes_changed','Observações do contato atualizadas.');
        }
        return $saved;
    }

    private function nullable(mixed $value): ?string { $value=trim((string)$value); return $value==='' ? null : $value; }

    private function validDocument(string $digits): bool
    {
        $length = strlen($digits);
        if (!in_array($length, [11, 14], true) || preg_match('/^(\d)\1+$/', $digits) === 1) return false;
        $baseLength = $length - 2;
        for ($digitIndex = $baseLength; $digitIndex < $length; $digitIndex++) {
            $sum = 0;
            if ($length === 11) {
                for ($index = 0; $index < $digitIndex; $index++) $sum += (int)$digits[$index] * ($digitIndex + 1 - $index);
                $check = (10 * $sum) % 11; if ($check === 10) $check = 0;
            } else {
                $weight = $digitIndex === 12 ? 5 : 6;
                for ($index = 0; $index < $digitIndex; $index++) { $sum += (int)$digits[$index] * $weight; $weight = $weight === 2 ? 9 : $weight - 1; }
                $remainder = $sum % 11; $check = $remainder < 2 ? 0 : 11 - $remainder;
            }
            if ((int)$digits[$digitIndex] !== $check) return false;
        }
        return true;
    }
}
