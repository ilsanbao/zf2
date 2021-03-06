<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Authentication
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Authentication\Adapter;
use Zend\Authentication\Adapter\AdapterInterface as AuthenticationAdapter,
    Zend\Authentication\Result as AuthenticationResult,
    Zend\Db\Adapter\Adapter as DbAdapter,
    Zend\Db\Sql\Select as DbSelect,
    Zend\Db\Sql\Expression,
    Zend\Db\ResultSet\ResultSet;

/**
 * @category   Zend
 * @package    Zend_Authentication
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class DbTable implements AuthenticationAdapter
{

    /**
     * Database Connection
     *
     * @var DbAdapter
     */
    protected $_zendDb = null;

    /**
     * @var DbSelect
     */
    protected $_dbSelect = null;

    /**
     * $_tableName - the table name to check
     *
     * @var string
     */
    protected $_tableName = null;

    /**
     * $_identityColumn - the column to use as the identity
     *
     * @var string
     */
    protected $_identityColumn = null;

    /**
     * $_credentialColumns - columns to be used as the credentials
     *
     * @var string
     */
    protected $_credentialColumn = null;

    /**
     * $_identity - Identity value
     *
     * @var string
     */
    protected $_identity = null;

    /**
     * $_credential - Credential values
     *
     * @var string
     */
    protected $_credential = null;

    /**
     * $_credentialTreatment - Treatment applied to the credential, such as MD5() or PASSWORD()
     *
     * @var string
     */
    protected $_credentialTreatment = null;

    /**
     * $_authenticateResultInfo
     *
     * @var array
     */
    protected $_authenticateResultInfo = null;

    /**
     * $_resultRow - Results of database authentication query
     *
     * @var array
     */
    protected $_resultRow = null;

    /**
     * $_ambiguityIdentity - Flag to indicate same Identity can be used with
     * different credentials. Default is FALSE and need to be set to true to
     * allow ambiguity usage.
     *
     * @var boolean
     */
    protected $_ambiguityIdentity = false;

    /**
     * __construct() - Sets configuration options
     *
     * @param  DbAdapter                $zendDb
     * @param  string                   $tableName
     * @param  string                   $identityColumn
     * @param  string                   $credentialColumn
     * @param  string                   $credentialTreatment
     * @return void
     */
    public function __construct(DbAdapter $zendDb, $tableName = null, $identityColumn = null,
                                $credentialColumn = null, $credentialTreatment = null)
    {
        $this->_zendDb = $zendDb;

        if (null !== $tableName) {
            $this->setTableName($tableName);
        }

        if (null !== $identityColumn) {
            $this->setIdentityColumn($identityColumn);
        }

        if (null !== $credentialColumn) {
            $this->setCredentialColumn($credentialColumn);
        }

        if (null !== $credentialTreatment) {
            $this->setCredentialTreatment($credentialTreatment);
        }
    }

    /**
     * setTableName() - set the table name to be used in the select query
     *
     * @param  string $tableName
     * @return DbTable Provides a fluent interface
     */
    public function setTableName($tableName)
    {
        $this->_tableName = $tableName;
        return $this;
    }

    /**
     * setIdentityColumn() - set the column name to be used as the identity column
     *
     * @param  string $identityColumn
     * @return DbTable Provides a fluent interface
     */
    public function setIdentityColumn($identityColumn)
    {
        $this->_identityColumn = $identityColumn;
        return $this;
    }

    /**
     * setCredentialColumn() - set the column name to be used as the credential column
     *
     * @param  string $credentialColumn
     * @return DbTable Provides a fluent interface
     */
    public function setCredentialColumn($credentialColumn)
    {
        $this->_credentialColumn = $credentialColumn;
        return $this;
    }

    /**
     * setCredentialTreatment() - allows the developer to pass a parameterized string that is
     * used to transform or treat the input credential data.
     *
     * In many cases, passwords and other sensitive data are encrypted, hashed, encoded,
     * obscured, or otherwise treated through some function or algorithm. By specifying a
     * parameterized treatment string with this method, a developer may apply arbitrary SQL
     * upon input credential data.
     *
     * Examples:
     *
     *  'PASSWORD(?)'
     *  'MD5(?)'
     *
     * @param  string $treatment
     * @return DbTable Provides a fluent interface
     */
    public function setCredentialTreatment($treatment)
    {
        $this->_credentialTreatment = $treatment;
        return $this;
    }

    /**
     * setIdentity() - set the value to be used as the identity
     *
     * @param  string $value
     * @return DbTable Provides a fluent interface
     */
    public function setIdentity($value)
    {
        $this->_identity = $value;
        return $this;
    }

    /**
     * setCredential() - set the credential value to be used, optionally can specify a treatment
     * to be used, should be supplied in parameterized form, such as 'MD5(?)' or 'PASSWORD(?)'
     *
     * @param  string $credential
     * @return DbTable Provides a fluent interface
     */
    public function setCredential($credential)
    {
        $this->_credential = $credential;
        return $this;
    }

    /**
     * setAmbiguityIdentity() - sets a flag for usage of identical identities
     * with unique credentials. It accepts integers (0, 1) or boolean (true,
     * false) parameters. Default is false.
     *
     * @param  int|bool $flag
     * @return DbTable Provides a fluent interface
     */
    public function setAmbiguityIdentity($flag)
    {
        if (is_integer($flag)) {
            $this->_ambiguityIdentity = (1 === $flag ? true : false);
        } elseif (is_bool($flag)) {
            $this->_ambiguityIdentity = $flag;
        }
        return $this;
    }

    /**
     * getAmbiguityIdentity() - returns TRUE for usage of multiple identical
     * identies with different credentials, FALSE if not used.
     *
     * @return bool
     */
    public function getAmbiguityIdentity()
    {
        return $this->_ambiguityIdentity;
    }

    /**
     * getDbSelect() - Return the preauthentication Db Select object for userland select query modification
     *
     * @return DbSelect
     */
    public function getDbSelect()
    {
        if ($this->_dbSelect == null) {
            $this->_dbSelect = new DbSelect();
        }
        return $this->_dbSelect;
    }

    /**
     * getResultRowObject() - Returns the result row as a stdClass object
     *
     * @param  string|array $returnColumns
     * @param  string|array $omitColumns
     * @return stdClass|boolean
     */
    public function getResultRowObject($returnColumns = null, $omitColumns = null)
    {
        if (!$this->_resultRow) {
            return false;
        }

        $returnObject = new \stdClass();

        if (null !== $returnColumns) {

            $availableColumns = array_keys($this->_resultRow);
            foreach ( (array) $returnColumns as $returnColumn) {
                if (in_array($returnColumn, $availableColumns)) {
                    $returnObject->{$returnColumn} = $this->_resultRow[$returnColumn];
                }
            }
            return $returnObject;

        } elseif (null !== $omitColumns) {

            $omitColumns = (array) $omitColumns;
            foreach ($this->_resultRow as $resultColumn => $resultValue) {
                if (!in_array($resultColumn, $omitColumns)) {
                    $returnObject->{$resultColumn} = $resultValue;
                }
            }
            return $returnObject;

        } else {

            foreach ($this->_resultRow as $resultColumn => $resultValue) {
                $returnObject->{$resultColumn} = $resultValue;
            }
            return $returnObject;

        }
    }

    /**
     * authenticate() - defined by Zend_Auth_Adapter_Interface.  This method is called to
     * attempt an authentication.  Previous to this call, this adapter would have already
     * been configured with all necessary information to successfully connect to a database
     * table and attempt to find a record matching the provided identity.
     *
     * @throws Exception if answering the authentication query is impossible
     * @return AuthenticationResult
     */
    public function authenticate()
    {
        $this->_authenticateSetup();
        $dbSelect = $this->_authenticateCreateSelect();
        $resultIdentities = $this->_authenticateQuerySelect($dbSelect);

        if ( ($authResult = $this->_authenticateValidateResultSet($resultIdentities)) instanceof AuthenticationResult) {
            return $authResult;
        }

        // At this point, ambiguity is already done. Loop, check and break on success.
        foreach ($resultIdentities as $identity) {
            $authResult = $this->_authenticateValidateResult($identity);
            if ($authResult->isValid()) {
                break;
            }
        }

        return $authResult;
    }

    /**
     * _authenticateSetup() - This method abstracts the steps involved with
     * making sure that this adapter was indeed setup properly with all
     * required pieces of information.
     *
     * @throws Exception\ExceptionInterface - in the event that setup was not done properly
     * @return true
     */
    protected function _authenticateSetup()
    {
        $exception = null;

        if ($this->_tableName == '') {
            $exception = 'A table must be supplied for the DbTable authentication adapter.';
        } elseif ($this->_identityColumn == '') {
            $exception = 'An identity column must be supplied for the DbTable authentication adapter.';
        } elseif ($this->_credentialColumn == '') {
            $exception = 'A credential column must be supplied for the DbTable authentication adapter.';
        } elseif ($this->_identity == '') {
            $exception = 'A value for the identity was not provided prior to authentication with DbTable.';
        } elseif ($this->_credential === null) {
            $exception = 'A credential value was not provided prior to authentication with DbTable.';
        }

        if (null !== $exception) {
            throw new Exception\RuntimeException($exception);
        }

        $this->_authenticateResultInfo = array(
            'code'     => AuthenticationResult::FAILURE,
            'identity' => $this->_identity,
            'messages' => array()
            );

        return true;
    }

    /**
     * _authenticateCreateSelect() - This method creates a Zend\Db\Sql\Select object that
     * is completely configured to be queried against the database.
     *
     * @return DbSelect
     */
    protected function _authenticateCreateSelect()
    {
        // build credential expression
        if (empty($this->_credentialTreatment) || (strpos($this->_credentialTreatment, '?') === false)) {
            $this->_credentialTreatment = '?';
        }

        $credentialExpression = new Expression(
            '(CASE WHEN '
            . $this->_zendDb->getPlatform()->quoteIdentifier($this->_credentialColumn)
            . ' = ' . $this->_credentialTreatment
            . ' THEN 1 ELSE 0 END) AS '
            . $this->_zendDb->getPlatform()->quoteIdentifier('zend_auth_credential_match')
        );

        // get select
        $dbSelect = clone $this->getDbSelect();
        $dbSelect->from($this->_tableName)
                 ->columns(array('*', $credentialExpression))
                 ->where($this->_zendDb->getPlatform()->quoteIdentifier($this->_identityColumn) . ' = ?');

        return $dbSelect;
    }

    /**
     * _authenticateQuerySelect() - This method accepts a Zend\Db\Sql\Select object and
     * performs a query against the database with that object.
     *
     * @param  DbSelect $dbSelect
     * @throws Exception - when an invalid select object is encountered
     * @return array
     */
    protected function _authenticateQuerySelect(DBSelect $dbSelect)
    {
        $statement = $this->_zendDb->createStatement();
        $dbSelect->prepareStatement($this->_zendDb, $statement);
        $resultSet = new ResultSet();
        try {
            $resultSet->setDataSource($statement->execute(array($this->_credential, $this->_identity)));
            $resultIdentities = $resultSet->toArray();
        } catch (\Exception $e) {
            throw new Exception\RuntimeException(
                'The supplied parameters to DbTable failed to '
                . 'produce a valid sql statement, please check table and column names '
                . 'for validity.', 0, $e
            );
        }
        return $resultIdentities;
    }

    /**
     * _authenticateValidateResultSet() - This method attempts to make
     * certain that only one record was returned in the resultset
     *
     * @param array $resultIdentities
     * @return true|Zend\Authentication\Result
     */
    protected function _authenticateValidateResultSet(array $resultIdentities)
    {

        if (count($resultIdentities) < 1) {
            $this->_authenticateResultInfo['code'] = AuthenticationResult::FAILURE_IDENTITY_NOT_FOUND;
            $this->_authenticateResultInfo['messages'][] = 'A record with the supplied identity could not be found.';
            return $this->_authenticateCreateAuthResult();
        } elseif (count($resultIdentities) > 1 && false === $this->getAmbiguityIdentity()) {
            $this->_authenticateResultInfo['code'] = AuthenticationResult::FAILURE_IDENTITY_AMBIGUOUS;
            $this->_authenticateResultInfo['messages'][] = 'More than one record matches the supplied identity.';
            return $this->_authenticateCreateAuthResult();
        }

        return true;
    }

    /**
     * _authenticateValidateResult() - This method attempts to validate that
     * the record in the resultset is indeed a record that matched the
     * identity provided to this adapter.
     *
     * @param  array $resultIdentity
     * @return AuthenticationResult
     */
    protected function _authenticateValidateResult($resultIdentity)
    {
        if ($resultIdentity['zend_auth_credential_match'] != '1') {
            $this->_authenticateResultInfo['code'] = AuthenticationResult::FAILURE_CREDENTIAL_INVALID;
            $this->_authenticateResultInfo['messages'][] = 'Supplied credential is invalid.';
            return $this->_authenticateCreateAuthResult();
        }

        unset($resultIdentity['zend_auth_credential_match']);
        $this->_resultRow = $resultIdentity;

        $this->_authenticateResultInfo['code'] = AuthenticationResult::SUCCESS;
        $this->_authenticateResultInfo['messages'][] = 'Authentication successful.';
        return $this->_authenticateCreateAuthResult();
    }

    /**
     * _authenticateCreateAuthResult() - Creates a Zend_Auth_Result object from
     * the information that has been collected during the authenticate() attempt.
     *
     * @return AuthenticationResult
     */
    protected function _authenticateCreateAuthResult()
    {
        return new AuthenticationResult(
            $this->_authenticateResultInfo['code'],
            $this->_authenticateResultInfo['identity'],
            $this->_authenticateResultInfo['messages']
        );
    }

}
