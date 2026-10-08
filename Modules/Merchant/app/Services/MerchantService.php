<?php

namespace Modules\Merchant\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Merchant\Exceptions\MerchantInUse;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;
use Modules\Merchant\Models\MerchantReward;
use Modules\Merchant\Support\BranchCodeGenerator;

/**
 * Merchants, their branches and rewards. Anything with redemptions stays (it is part of what
 * is owed to the merchant): it can be deactivated, not deleted.
 */
class MerchantService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly BranchCodeGenerator $codes,
    ) {}

    /** @param array<string, mixed> $data */
    public function saveMerchant(Merchant $merchant, array $data): Merchant
    {
        $merchant->fill($data)->save();

        return $merchant;
    }

    /** @throws MerchantInUse */
    public function deleteMerchant(Merchant $merchant): void
    {
        $this->db->transaction(function () use ($merchant) {
            if ($merchant->redemptions()->exists()) {
                throw new MerchantInUse(__(':name has redemptions, so it can only be deactivated.', ['name' => $merchant->name]));
            }
            $merchant->delete(); // branches and rewards go with it (cascade)
        });
    }

    /** New branches get a fresh code; it is shown to the administrator to hand over. */
    public function createBranch(Merchant $merchant, array $data): MerchantBranch
    {
        $branch = new MerchantBranch($data);
        $branch->merchant()->associate($merchant);
        $branch->code = $this->codes->generate();
        $branch->code_changed_at = now();
        $branch->save();

        return $branch;
    }

    /** @param array<string, mixed> $data */
    public function updateBranch(MerchantBranch $branch, array $data): MerchantBranch
    {
        $branch->fill($data)->save();

        return $branch;
    }

    /** Replace a branch's code (e.g. staff left); the old one stops working at once. */
    public function regenerateCode(MerchantBranch $branch): string
    {
        $branch->code = $this->codes->generate();
        $branch->code_changed_at = now();
        $branch->save();

        return $branch->code;
    }

    /** @throws MerchantInUse */
    public function deleteBranch(MerchantBranch $branch): void
    {
        if ($branch->redemptions()->exists()) {
            throw new MerchantInUse(__(':name has redemptions, so it can only be deactivated.', ['name' => $branch->name]));
        }
        $branch->delete();
    }

    /** @param array<string, mixed> $data */
    public function saveReward(Merchant $merchant, MerchantReward $reward, array $data): MerchantReward
    {
        $reward->fill($data);
        $reward->merchant()->associate($merchant);
        $reward->save();

        return $reward;
    }

    /** Past redemptions keep the reward's name and amounts (snapshots), so this is always allowed. */
    public function deleteReward(MerchantReward $reward): void
    {
        $reward->delete();
    }
}
