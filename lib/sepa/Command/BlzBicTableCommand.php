<?php

namespace Scs\Common\BillingBundle\Command;

use Doctrine\ORM\EntityManager;
use Scs\Common\BillingBundle\Entity\BlzBic;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Wandelt die Text-Datei der Bundesbank in eine Datenbanktabelle um.
 * Download der aktuellsten Datei:
 * http://www.bundesbank.de/Redaktion/DE/Standardartikel/Aufgaben/Unbarer_Zahlungsverkehr/bankleitzahlen_download.html
 */
class BlzBicTableCommand extends ContainerAwareCommand
{

	/** @var EntityManager $em */
	private $em;

	protected function configure()
	{
		$this->setName('scscommon:blzbictable:update')
			->setDescription('Erstellt die BLZ zu BIC Tabelle')
			->addArgument(
				'file', InputArgument::REQUIRED,
				'Gib die Text-Datei der Bundesbank an'
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var \Doctrine\Bundle\DoctrineBundle\Registry $doctrine */
		$doctrine = $this->getContainer()->get('doctrine');
		$this->em = $doctrine->getManager();

		$filename = $input->getArgument('file');
		try
		{
			$file = new File($filename);
		}
		catch (FileNotFoundException $e)
		{
			$output->writeln(sprintf('error: file "%s" not found', $filename));
			return 1;
		}

		$this->truncateTable();

		$fp = $file->openFile();
		$fp->setMaxLineLen(169);
		$cnt = 0;
		foreach ($fp as $line)
		{
			$success = $this->processLine($line);
			if ($success)
			{
				$cnt++;
			}
		}

		if ($cnt)
		{
			$this->em->flush();
		}
		$output->writeln(
			sprintf('Es wurden %d Datensaetze gespeichert.', $cnt)
		);

		return 0;
	}

	/**
	 * Erstellt aus der Zeile eine Entity und merkt sie zum speichern vor.
	 *
	 * @param string $line
	 * @return bool
	 */
	private function processLine($line)
	{
		$ptr = 0;
		$fields = array(
			'blz' => BlzBic::LENGTH_BLZ,
			'type' => 1,
			'name' => BlzBic::LENGTH_NAME,
			'plz' => BlzBic::LENGTH_PLZ,
			'city' => BlzBic::LENGTH_CITY,
			'shortname' => BlzBic::LENGTH_SHORTNAME,
			'pan' => 5,
			'bic' => BlzBic::LENGTH_BIC
		);
		$data = array();
		foreach ($fields as $key => $length)
		{
			$data[$key] = rtrim(substr($line, $ptr, $length));
			$ptr += $length;
		}

		if (1 != $data['type'] or !$data['bic'])
		{
			return false;
		}

		$blzbic = new BlzBic();
		$blzbic->setBlz($data['blz'])
			->setName($data['name'])
			->setPlz($data['plz'])
			->setCity($data['city'])
			->setShortname($data['shortname'])
			->setBic($data['bic']);
		$this->em->persist($blzbic);
		return true;
	}

	/**
	 * Leert die Tabelle
	 *
	 * @throws \Exception
	 */
	private function truncateTable()
	{
		if (is_null($this->em))
		{
			throw new \Exception('entity manager not set');
		}

		$cmd = $this->em->getClassMetadata('ScsCommonBillingBundle:BlzBic');
		$connection = $this->em->getConnection();
		$dbPlatform = $connection->getDatabasePlatform();
		$connection->beginTransaction();
		try
		{
			$connection->query('SET FOREIGN_KEY_CHECKS=0');
			$q = $dbPlatform->getTruncateTableSQL($cmd->getTableName());
			$connection->executeQuery($q);
			$connection->query('SET FOREIGN_KEY_CHECKS=1');
			$connection->commit();
		}
		catch (\Exception $e)
		{
			$connection->rollBack();
		}
	}

}