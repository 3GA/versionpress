<?php

abstract class ObservableStorage implements EntityStorage {
    /**
     * @var callable[]
     */
    private $onChangeListeners;

    function addChangeListener($callback) {
        $this->onChangeListeners[] = $callback;
    }

    protected function callOnChangeListeners(ChangeInfo $changeInfo) {
        foreach ($this->onChangeListeners as $onChangeListener) {
            call_user_func($onChangeListener, $changeInfo);
        }
    }
}