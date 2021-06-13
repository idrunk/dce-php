<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/14 5:36
 */

namespace dce\model\validator\ending;

use dce\db\active\ActiveQuery;
use dce\db\active\ActiveRecord;
use dce\model\validator\TypeEnding;
use dce\model\validator\ValidatorException;

class UniqueValidator extends TypeEnding {
    protected array $combined;

    protected function check(string|int|float|null|false $value):ValidatorException|null {
        if ($this->model instanceof ActiveRecord) {
            $combined = $this->getProperty('combined');
            $combinedNames = $combined->value ?? [];
            $whereConditions = [$this->modelPropertyName, '=', $value];
            foreach ($combinedNames as $combinedName) {
                $whereConditions[] = [$combinedName, '=', $this->model->{$this->model::toModelKey($combinedName)}];
            }
            $activeRecordClass = $this->model::class;
            /** @var ActiveQuery $activeQuery */
            $activeQuery = $activeRecordClass::query();
            $rows = $activeQuery->where($whereConditions)->limit(2)->select();
            if (! empty($rows)) { // 如果找到了记录
                if ($this->model->isCreateByQuery()) { // 如果创建自查询, 且如果查询到的数据大于两条, 或查出的主键与当前主键不同, 则表示有重复
                    if (count($rows) > 1 || ! empty(array_diff_assoc($rows[0]->getPkValues(), $this->model->getPkValues()))) {
                        $this->addError($this->getGeneralError($combined->error ?? null, lang(ValidatorException::CANNOT_REPEAT)));
                    }
                } else { // 如果非创建自查询, 则为插入, 则只要有查到即为重复的
                    $this->addError($this->getGeneralError($combined->error ?? null, lang(ValidatorException::CANNOT_REPEAT)));
                }
            }
        } else {
            $this->addError(lang(ValidatorException::NOT_SUPPORT_UNIQUE));
        }

        return $this->getError();
    }
}
